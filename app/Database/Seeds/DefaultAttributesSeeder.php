<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DefaultAttributesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name' => 'Size',
                'values' => json_encode(['XS','S','M','L','XL','XXL']),
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Color',
                'values' => json_encode(['Red','Green','Blue','Black','White','Yellow']),
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Pattern',
                'values' => json_encode(['Plain','Striped','Checked','Printed']),
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
        ];

        $this->db->table('product_attributes')->insertBatch($data);
    }
}
