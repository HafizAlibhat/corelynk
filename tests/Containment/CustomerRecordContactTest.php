<?php

namespace Tests\Containment;

use App\Controllers\Customers;
use App\Models\CustomerPersonModel;
use ReflectionMethod;
use Tests\Support\ContainmentTestCase;

/**
 * Customers created before customer_persons existed carry their contact details on the
 * customer record itself. Opening such a customer promotes those details into a real,
 * editable contact -- once, and never on top of contacts that already exist.
 */
final class CustomerRecordContactTest extends ContainmentTestCase
{
    private function makeCustomer(array $extra = []): array
    {
        $row = array_merge([
            'customer_code' => 'TEST-BF-' . uniqid(),
            'name'          => 'Backfill Fixture',
            'type'          => 'company',
            'status'        => 'active',
        ], $extra);

        $this->db->table('customers')->insert($row);
        $row['id'] = (int) $this->db->insertID();

        return $row;
    }

    private function backfill(array $customer): void
    {
        $ref = new ReflectionMethod(Customers::class, 'backfillRecordContact');
        $ref->setAccessible(true);
        $ref->invoke(new Customers(), $customer);
    }

    /** @return array<int, array<string, mixed>> */
    private function contactsOf(int $customerId): array
    {
        return (new CustomerPersonModel())->where('customer_id', $customerId)->orderBy('id', 'ASC')->findAll();
    }

    public function testRecordContactBecomesARealPrimaryContact(): void
    {
        $customer = $this->makeCustomer(['email' => 'legacy@example.com', 'phone' => '021-111']);

        $this->backfill($customer);

        $rows = $this->contactsOf($customer['id']);
        self::assertSame(1, count($rows));
        self::assertSame('Backfill Fixture', $rows[0]['name']);
        self::assertSame('legacy@example.com', $rows[0]['email']);
        self::assertSame('021-111', $rows[0]['phone']);
        self::assertSame(1, (int) $rows[0]['is_primary_contact'], 'the record contact is the primary one');
    }

    public function testRunningTwiceDoesNotDuplicate(): void
    {
        $customer = $this->makeCustomer(['email' => 'legacy@example.com']);

        $this->backfill($customer);
        $this->backfill($customer);

        self::assertSame(1, count($this->contactsOf($customer['id'])), 'backfill must be idempotent');
    }

    public function testRecordContactIsAddedAlongsideContactsEnteredByHand(): void
    {
        $customer = $this->makeCustomer(['email' => 'legacy@example.com', 'phone' => '021-111']);
        $this->db->table('customer_persons')->insert([
            'customer_id'        => $customer['id'],
            'name'               => 'Already Here',
            'email'              => 'someone.else@example.com',
            'is_primary_contact' => 1,
            'is_active'          => 1,
        ]);

        $this->backfill($customer);

        $rows = $this->contactsOf($customer['id']);
        self::assertSame(2, count($rows), 'the person on the record is a contact too');
        self::assertSame('Already Here', $rows[0]['name']);
        self::assertSame(1, (int) $rows[0]['is_primary_contact'], 'a primary chosen by hand keeps the flag');
        self::assertSame('Backfill Fixture', $rows[1]['name']);
        self::assertSame(0, (int) $rows[1]['is_primary_contact']);
    }

    public function testAContactWithTheSameEmailIsNotPromotedTwice(): void
    {
        $customer = $this->makeCustomer(['email' => 'Legacy@example.com', 'phone' => '021-111']);
        $this->db->table('customer_persons')->insert([
            'customer_id' => $customer['id'],
            'name'        => 'Same Person',
            'email'       => 'legacy@example.com',
            'is_active'   => 1,
        ]);

        $this->backfill($customer);

        self::assertSame(1, count($this->contactsOf($customer['id'])), 'matching details mean it is already there');
    }

    public function testCustomerWithNoContactDetailsGetsNothing(): void
    {
        $customer = $this->makeCustomer();

        $this->backfill($customer);

        self::assertSame(0, count($this->contactsOf($customer['id'])), 'nothing captured means nothing to promote');
    }
}
