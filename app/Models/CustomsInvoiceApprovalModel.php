<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomsInvoiceApprovalModel extends Model
{
    protected $table = 'customs_invoice_approvals';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $allowedFields = [
        'uuid',
        'customs_invoice_id',
        'customs_invoice_version_id',
        'approval_status',
        'approval_channel',
        'requested_to_name',
        'requested_to_email',
        'request_message',
        'decision_comment',
        'token_hash',
        'token_expires_at',
        'requested_by_user_id',
        'decided_by_user_id',
        'requested_at',
        'decided_at',
    ];
}
