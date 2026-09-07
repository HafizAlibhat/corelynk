<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentAttachments extends Migration
{
    public function up()
    {
        // Generic attachments table for any document type (journal/cheque/invoice/etc)
        if (! $this->db->tableExists('document_attachments')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'document_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
                'document_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'file_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
                'original_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
                'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'uploaded_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'uploaded_at' => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['document_type', 'document_id']);
            $this->forge->createTable('document_attachments', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('document_attachments', true);
    }
}
