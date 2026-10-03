<?php

namespace Tests\Containment;

use App\Controllers\Customers;
use ReflectionMethod;
use Tests\Support\ContainmentTestCase;

/**
 * The customer detail page edits contacts and addresses in place. Those endpoints
 * must keep two invariants no matter what the page posts:
 *
 *   - exactly one primary contact / one default address per customer;
 *   - a row belonging to another customer is never touched.
 */
final class CustomerRelationEndpointTest extends ContainmentTestCase
{
    private function makeCustomer(string $tag): int
    {
        $this->db->table('customers')->insert([
            'customer_code' => 'TEST-REL-' . $tag . '-' . uniqid(),
            'name'          => 'Relation Fixture ' . $tag,
            'type'          => 'company',
            'status'        => 'active',
        ]);

        return (int) $this->db->insertID();
    }

    private function makeContact(int $customerId, string $name, int $primary): int
    {
        $this->db->table('customer_persons')->insert([
            'customer_id'        => $customerId,
            'name'               => $name,
            'is_primary_contact' => $primary,
            'is_active'          => 1,
        ]);

        return (int) $this->db->insertID();
    }

    private function makeAddress(int $customerId, string $line1, int $default): int
    {
        $this->db->table('customer_addresses')->insert([
            'customer_id' => $customerId,
            'line1'       => $line1,
            'is_default'  => $default,
        ]);

        return (int) $this->db->insertID();
    }

    private function call(string $method, array $args)
    {
        $ref = new ReflectionMethod(Customers::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(new Customers(), $args);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(string $table, int $customerId): array
    {
        return $this->db->table($table)->where('customer_id', $customerId)->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function testPromotingAContactDemotesEveryOtherOne(): void
    {
        $customerId = $this->makeCustomer('C');
        $first      = $this->makeContact($customerId, 'First', 1);
        $second     = $this->makeContact($customerId, 'Second', 0);

        $this->call('makeContactPrimary', [$customerId, $second]);

        $rows = $this->rows('customer_persons', $customerId);
        self::assertSame(0, (int) $rows[0]['is_primary_contact'], 'the old primary must be demoted');
        self::assertSame(1, (int) $rows[1]['is_primary_contact']);
        self::assertSame($first, (int) $rows[0]['id']);
    }

    public function testPromotingAnAddressDemotesEveryOtherOne(): void
    {
        $customerId = $this->makeCustomer('A');
        $this->makeAddress($customerId, 'Street 1', 1);
        $second = $this->makeAddress($customerId, 'Street 2', 0);

        $this->call('makeAddressDefault', [$customerId, $second]);

        $rows = $this->rows('customer_addresses', $customerId);
        self::assertSame(0, (int) $rows[0]['is_default'], 'the old default must be demoted');
        self::assertSame(1, (int) $rows[1]['is_default']);
    }

    public function testAnotherCustomersRowsAreLeftAlone(): void
    {
        $mine     = $this->makeCustomer('M');
        $theirs   = $this->makeCustomer('T');
        $myRow    = $this->makeContact($mine, 'Mine', 1);
        $theirRow = $this->makeContact($theirs, 'Theirs', 1);

        // Promoting inside one customer must not clear the other customer's primary.
        $this->call('makeContactPrimary', [$mine, $myRow]);

        $other = $this->rows('customer_persons', $theirs);
        self::assertSame(1, count($other));
        self::assertSame($theirRow, (int) $other[0]['id']);
        self::assertSame(1, (int) $other[0]['is_primary_contact'], "another customer's primary must survive");
    }
}
