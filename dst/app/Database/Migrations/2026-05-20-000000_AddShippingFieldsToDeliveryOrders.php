<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add comprehensive shipping, tracking and delivery fields to delivery_orders table.
 * Includes: public_id, shipping vendor/service details, tracking info, delivery status, and PO/Bill links.
 */
class AddShippingFieldsToDeliveryOrders extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('delivery_orders')) {
            $fields = $this->db->getFieldNames('delivery_orders');

            // Add public_id if not present (supports PublicIdTrait)
            if (!in_array('public_id', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'public_id' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 36,
                        'null'       => true,
                        'unique'     => true,
                        'after'      => 'id',
                    ],
                ]);
            }

            // Shipping vendor and service IDs
            if (!in_array('shipping_vendor_id', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipping_vendor_id' => [
                        'type'       => 'INT',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'status',
                    ],
                ]);
            }

            if (!in_array('shipping_service_id', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipping_service_id' => [
                        'type'       => 'INT',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'shipping_vendor_id',
                    ],
                ]);
            }

            // Shipment weight and cost
            if (!in_array('final_weight_kg', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'final_weight_kg' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '10,2',
                        'null'       => true,
                        'after'      => 'shipping_service_id',
                    ],
                ]);
            }

            if (!in_array('shipping_cost_pkr', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipping_cost_pkr' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '12,2',
                        'null'       => true,
                        'after'      => 'final_weight_kg',
                    ],
                ]);
            }

            // Tracking information
            if (!in_array('tracking_number', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'tracking_number' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                        'after'      => 'shipping_cost_pkr',
                    ],
                ]);
            }

            if (!in_array('tracking_url', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'tracking_url' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 500,
                        'null'       => true,
                        'after'      => 'tracking_number',
                    ],
                ]);
            }

            // Destination and notes
            if (!in_array('destination_country', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'destination_country' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                        'after'      => 'tracking_url',
                    ],
                ]);
            }

            if (!in_array('shipping_notes', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipping_notes' => [
                        'type'   => 'TEXT',
                        'null'   => true,
                        'after'  => 'destination_country',
                    ],
                ]);
            }

            // Shipped and estimated delivery
            if (!in_array('shipped_at', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipped_at' => [
                        'type'   => 'DATETIME',
                        'null'   => true,
                        'after'  => 'shipping_notes',
                    ],
                ]);
            }

            if (!in_array('estimated_delivery_days', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'estimated_delivery_days' => [
                        'type'       => 'INT',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'shipped_at',
                    ],
                ]);
            }

            // Delivery status tracking
            if (!in_array('delivery_status', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'delivery_status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                        'comment'    => 'delivered, lost, customer_refused, damaged_in_transit, returned_to_sender, delayed, partial_delivery',
                        'after'      => 'estimated_delivery_days',
                    ],
                ]);
            }

            if (!in_array('delivery_confirmed_at', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'delivery_confirmed_at' => [
                        'type'   => 'DATETIME',
                        'null'   => true,
                        'after'  => 'delivery_status',
                    ],
                ]);
            }

            if (!in_array('delivery_notes', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'delivery_notes' => [
                        'type'   => 'TEXT',
                        'null'   => true,
                        'after'  => 'delivery_confirmed_at',
                    ],
                ]);
            }

            // PO and Bill links
            if (!in_array('shipping_po_id', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipping_po_id' => [
                        'type'       => 'INT',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'delivery_notes',
                    ],
                ]);
            }

            if (!in_array('shipping_bill_id', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'shipping_bill_id' => [
                        'type'       => 'INT',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'shipping_po_id',
                    ],
                ]);
            }

            // Legacy parcel image (single image field)
            if (!in_array('parcel_image', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'parcel_image' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                        'after'      => 'shipping_bill_id',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_orders')) {
            $fields = $this->db->getFieldNames('delivery_orders');
            $fieldsToRemove = [
                'public_id',
                'shipping_vendor_id',
                'shipping_service_id',
                'final_weight_kg',
                'shipping_cost_pkr',
                'tracking_number',
                'tracking_url',
                'destination_country',
                'shipping_notes',
                'shipped_at',
                'estimated_delivery_days',
                'delivery_status',
                'delivery_confirmed_at',
                'delivery_notes',
                'shipping_po_id',
                'shipping_bill_id',
                'parcel_image',
            ];

            $fieldsToDropNow = array_filter($fieldsToRemove, fn($f) => in_array($f, $fields));
            if (!empty($fieldsToDropNow)) {
                $this->forge->dropColumn('delivery_orders', array_values($fieldsToDropNow));
            }
        }
    }
}
