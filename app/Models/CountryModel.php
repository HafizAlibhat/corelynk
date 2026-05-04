<?php

namespace App\Models;

use CodeIgniter\Model;

class CountryModel extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'iso_code'];
    public function getList()
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }
}
