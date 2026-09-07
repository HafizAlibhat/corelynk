<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentAttachmentModel extends Model
{
    protected $table = 'document_attachments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'document_type',
        'document_id',
        'file_path',
        'original_name',
        'mime_type',
        'uploaded_by',
        'uploaded_at',
        'file_size',
    ];

    protected $useTimestamps = false;
}
