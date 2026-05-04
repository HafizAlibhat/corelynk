<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'action', 'module', 'resource_id',
        'details', 'ip_address', 'user_agent',
    ];

    protected $useTimestamps = false; // we set created_at manually

    /**
     * Record an audit event.
     */
    public static function record(
        string $action,
        ?int   $userId    = null,
        ?string $module   = null,
        ?int   $resourceId = null,
        ?array $details   = null
    ): void {
        $request = service('request');
        $db = \Config\Database::connect();
        $db->table('audit_log')->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'module'      => $module,
            'resource_id' => $resourceId,
            'details'     => $details ? json_encode($details) : null,
            'ip_address'  => $request->getIPAddress(),
            'user_agent'  => substr((string) $request->getUserAgent(), 0, 255),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Paginated list with optional filters.
     */
    public function search(?string $action = null, ?int $userId = null, ?string $from = null, ?string $to = null, int $perPage = 50)
    {
        $builder = $this->select('audit_log.*, u.username, u.first_name, u.last_name')
                        ->join('users u', 'u.id = audit_log.user_id', 'left')
                        ->orderBy('audit_log.created_at', 'DESC');
        if ($action) $builder->where('audit_log.action', $action);
        if ($userId) $builder->where('audit_log.user_id', $userId);
        if ($from)   $builder->where('audit_log.created_at >=', $from);
        if ($to)     $builder->where('audit_log.created_at <=', $to . ' 23:59:59');
        return $builder->paginate($perPage);
    }
}
