<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table            = 'permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['module', 'action', 'description'];

    protected $useTimestamps = false;

    /**
     * Return permissions grouped by module:
     * ['invoices' => [['id'=>1,'action'=>'read',...], ...], ...]
     */
    public function groupedByModule(): array
    {
        $all = $this->orderBy('module')->orderBy('action')->findAll();
        $grouped = [];
        foreach ($all as $p) {
            $grouped[$p['module']][] = $p;
        }
        return $grouped;
    }

    /**
     * Return a flat slug map:  "invoices.read" => id
     */
    public function slugMap(): array
    {
        $all = $this->findAll();
        $map = [];
        foreach ($all as $p) {
            $map[$p['module'] . '.' . $p['action']] = (int) $p['id'];
        }
        return $map;
    }
}
