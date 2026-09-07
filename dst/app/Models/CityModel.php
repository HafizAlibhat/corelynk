<?php

namespace App\Models;

use CodeIgniter\Model;

class CityModel extends Model
{
    protected $table = 'cities';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['state_id', 'name'];
    public function getByState($stateId)
    {
        return $this->where('state_id', $stateId)->orderBy('name','ASC')->findAll();
    }
}
