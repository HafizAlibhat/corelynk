<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTagsAndDocumentTags extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('tags')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => false,
                ],
                'slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                    'null'       => false,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('slug');
            $this->forge->createTable('tags', true);

            if ($db->tableExists('permissions')) {
                $actions = ['read', 'write', 'edit', 'delete'];
                foreach ($actions as $action) {
                    $existing = $db->table('permissions')
                        ->where('module', 'tags')
                        ->where('action', $action)
                        ->get()
                        ->getRowArray();

                    if (! $existing) {
                        $db->table('permissions')->insert([
                            'module' => 'tags',
                            'action' => $action,
                            'description' => ucfirst($action) . ' access to tags and document tag assignments',
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                if ($db->tableExists('roles') && $db->tableExists('role_permissions')) {
                    $adminRole = $db->table('roles')->where('slug', 'admin')->get()->getRowArray();
                    if ($adminRole) {
                        $permissionRows = $db->table('permissions')->where('module', 'tags')->get()->getResultArray();
                        foreach ($permissionRows as $permRow) {
                            $exists = $db->table('role_permissions')
                                ->where('role_id', (int) $adminRole['id'])
                                ->where('permission_id', (int) $permRow['id'])
                                ->get()
                                ->getRowArray();

                            if (! $exists) {
                                $db->table('role_permissions')->insert([
                                    'role_id' => (int) $adminRole['id'],
                                    'permission_id' => (int) $permRow['id'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                ]);
                            }
                        }
                    }
                }
            }
        }

        if (! $db->tableExists('document_tags')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'tag_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                ],
                'document_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                    'null'       => false,
                ],
                'document_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['document_type', 'document_id']);
            $this->forge->addKey('tag_id');
            $this->forge->addUniqueKey(['tag_id', 'document_type', 'document_id']);
            $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('document_tags', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('document_tags')) {
            $this->forge->dropTable('document_tags', true);
        }
        if ($this->db->tableExists('tags')) {
            $this->forge->dropTable('tags', true);
        }

        if ($this->db->tableExists('permissions') && $this->db->tableExists('role_permissions')) {
            $permissionIds = array_map(
                static fn(array $row): int => (int) $row['id'],
                $this->db->table('permissions')->where('module', 'tags')->get()->getResultArray()
            );

            if (! empty($permissionIds)) {
                $this->db->table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
                $this->db->table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }
    }
}
