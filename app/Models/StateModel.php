<?php

namespace App\Models;

use CodeIgniter\Model;

class StateModel extends Model
{
    protected $table = 'states';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['country_id', 'name'];
    public function getByCountry($countryId)
    {
        return $this->where('country_id', $countryId)->orderBy('name','ASC')->findAll();
    }
}
