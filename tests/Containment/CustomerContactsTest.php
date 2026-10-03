<?php

namespace Tests\Containment;

use App\Controllers\Customers;
use App\Models\CustomerPersonModel;
use ReflectionMethod;
use Tests\Support\ContainmentTestCase;

/**
 * A customer ships to more than one person: a consignee abroad, a buyer at head
 * office, an agent. The edit form posts them as a repeater, and Customers::
 * syncCustomerPersons() has to insert, update and delete them in one pass while
 * keeping exactly one primary contact.
 */
final class CustomerContactsTest extends ContainmentTestCase
{
    private function makeCustomer(): int
    {
        $this->db->table('customers')->insert([
            'customer_code' => 'TEST-CON-' . uniqid(),
            'name'          => 'Contacts Fixture',
            'type'          => 'company',
            'status'        => 'active',
        ]);

        return (int) $this->db->insertID();
    }

    private function sync(int $customerId, array $post): void
    {
        $method = new ReflectionMethod(Customers::class, 'syncCustomerPersons');
        $method->setAccessible(true);
        $method->invoke(new Customers(), $customerId, $post);
    }

    /** @return array<int, array<string, mixed>> */
    private function contactsOf(int $customerId): array
    {
        return (new CustomerPersonModel())
            ->where('customer_id', $customerId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function testContactsAreInsertedUpdatedAndDeletedInOnePass(): void
    {
        $customerId = $this->makeCustomer();

        // Two people, the second one marked primary. The blank third row is an
        // abandoned repeater slot and must not become an empty contact.
        $this->sync($customerId, [
            'contacts' => [
                0 => ['id' => '', 'name' => 'Ali Raza',  'title' => 'Buyer',     'email' => 'ali@x.com', 'phone' => '111', 'mobile' => ''],
                1 => ['id' => '', 'name' => 'Sara Khan', 'title' => 'Consignee', 'email' => '',          'phone' => '',    'mobile' => '222'],
                2 => ['id' => '', 'name' => '',          'title' => '',          'email' => '',          'phone' => '',    'mobile' => ''],
            ],
            'primary_contact_idx' => '1',
        ]);

        $rows = $this->contactsOf($customerId);
        self::assertSame(2, count($rows), 'blank repeater rows must be skipped');
        self::assertSame('Ali Raza', $rows[0]['name']);
        self::assertSame('Consignee', $rows[1]['title']);
        self::assertSame(0, (int) $rows[0]['is_primary_contact']);
        self::assertSame(1, (int) $rows[1]['is_primary_contact'], 'the posted index decides the primary');

        // Rename the first, hand it the primary flag, drop the second.
        $this->sync($customerId, [
            'contacts' => [
                0 => ['id' => $rows[0]['id'], 'name' => 'Ali R.', 'title' => 'Buyer', 'email' => 'ali@x.com', 'phone' => '111', 'mobile' => ''],
            ],
            'primary_contact_idx'  => '0',
            'deleted_contact_ids'  => [$rows[1]['id']],
        ]);

        $after = $this->contactsOf($customerId);
        self::assertSame(1, count($after), 'removed contact must be deleted');
        self::assertSame('Ali R.', $after[0]['name'], 'existing contact must be updated, not duplicated');
        self::assertSame((int) $rows[0]['id'], (int) $after[0]['id']);
        self::assertSame(1, (int) $after[0]['is_primary_contact']);
    }
}
