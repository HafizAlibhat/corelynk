<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomsInvoiceAuditLogModel extends Model
{
    protected $table = 'customs_invoice_audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'uuid',
        'customs_invoice_id',
        'customs_invoice_version_id',
        'event_type',
        'field_path',
        'before_value',
        'after_value',
        'diff_json',
        'actor_user_id',
        'actor_role',
        'actor_ip',
        'actor_user_agent',
        'correlation_id',
        'created_at',
    ];
}
