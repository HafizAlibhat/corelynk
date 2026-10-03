<?php

/**
 * Customer address/contact snapshots for sales documents.
 *
 * A document (quotation, sales order, customer invoice) stores the address and
 * contact it was issued with in its `customer_snapshot` JSON column. Rendering
 * reads the snapshot, never the live customer, so editing the customer profile
 * cannot rewrite documents that are already out of the door.
 *
 * Documents created before this column existed have no snapshot; those fall back
 * to the live primary address so nothing renders blank.
 */

if (! function_exists('customer_primary_address')) {
    /**
     * The address a document should use: default first, then billing, then
     * shipping, then oldest. `is_default` is what the customer profile toggles.
     */
    function customer_primary_address(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        try {
            $addr = (new \App\Models\CustomerAddressModel())->primaryFor($customerId);
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($addr)) {
            return [];
        }

        if (empty($addr['country_name']) && ! empty($addr['country_id'])) {
            try {
                $country = (new \App\Models\CountryModel())->find((int) $addr['country_id']);
                $addr['country_name'] = $country['name'] ?? null;
            } catch (\Throwable $e) {
                // country lookup is cosmetic
            }
        }

        return $addr;
    }
}

if (! function_exists('customer_snapshot_capture')) {
    /**
     * Build the snapshot payload for a customer as they are right now.
     *
     * @return array<string, mixed>
     */
    function customer_snapshot_capture(int $customerId): array
    {
        $customer = [];
        try {
            $customer = (new \App\Models\CustomerModel())->find($customerId) ?: [];
        } catch (\Throwable $e) {
            $customer = [];
        }

        $addr = customer_primary_address($customerId);

        return [
            'customer_id'   => $customerId,
            'address_id'    => isset($addr['id']) ? (int) $addr['id'] : null,
            'name'          => $customer['name'] ?? '',
            'customer_code' => $customer['customer_code'] ?? '',
            'company_name'  => $customer['company_name'] ?? '',
            'label'         => $addr['label'] ?? '',
            'line1'         => $addr['line1'] ?? '',
            'line2'         => $addr['line2'] ?? '',
            'city_name'     => $addr['city_name'] ?? '',
            'state_name'    => $addr['state_name'] ?? '',
            'country_name'  => $addr['country_name'] ?? '',
            'postal_code'   => $addr['postal_code'] ?? '',
            'email'         => $customer['email'] ?? '',
            'phone'         => $customer['phone'] ?? '',
            'mobile'        => $customer['mobile'] ?? '',
            'captured_at'   => date('Y-m-d H:i:s'),
        ];
    }
}

if (! function_exists('customer_snapshot_json')) {
    /** Encoded snapshot ready to be written to a document row, or null. */
    function customer_snapshot_json(int $customerId): ?string
    {
        $snap = customer_snapshot_capture($customerId);
        if ($customerId <= 0) {
            return null;
        }

        return json_encode($snap);
    }
}

if (! function_exists('customer_snapshot_decode')) {
    /**
     * Decode a document's stored snapshot.
     *
     * @param array<string, mixed>|null $document
     *
     * @return array<string, mixed>
     */
    function customer_snapshot_decode(?array $document): array
    {
        $raw = $document['customer_snapshot'] ?? null;
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (! function_exists('customer_snapshot_address')) {
    /**
     * The address to render for a document: its snapshot, else (legacy rows)
     * the live primary address.
     *
     * @param array<string, mixed>|null $document
     *
     * @return array<string, mixed>
     */
    function customer_snapshot_address(?array $document, int $customerId): array
    {
        $snap = customer_snapshot_decode($document);

        if ($snap === []) {
            return customer_primary_address($customerId);
        }

        return [
            'id'           => $snap['address_id'] ?? null,
            'label'        => $snap['label'] ?? '',
            'line1'        => $snap['line1'] ?? '',
            'line2'        => $snap['line2'] ?? '',
            'city_name'    => $snap['city_name'] ?? '',
            'state_name'   => $snap['state_name'] ?? '',
            'country_name' => $snap['country_name'] ?? '',
            'postal_code'  => $snap['postal_code'] ?? '',
        ];
    }
}

if (! function_exists('customer_snapshot_contact')) {
    /**
     * Overlay the snapshot's contact details on the live customer row, so views
     * that read $customer['phone'] / ['email'] show what the document was issued
     * with. Live values are kept for documents without a snapshot.
     *
     * @param array<string, mixed>|null $document
     * @param array<string, mixed>|null $customer
     *
     * @return array<string, mixed>
     */
    function customer_snapshot_contact(?array $document, ?array $customer): array
    {
        $customer = is_array($customer) ? $customer : [];
        $snap     = customer_snapshot_decode($document);

        if ($snap === []) {
            return $customer;
        }

        foreach (['name', 'customer_code', 'company_name', 'email', 'phone', 'mobile'] as $field) {
            if (isset($snap[$field]) && $snap[$field] !== '') {
                $customer[$field] = $snap[$field];
            }
        }

        return $customer;
    }
}
