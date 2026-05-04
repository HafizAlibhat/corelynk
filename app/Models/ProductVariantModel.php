<?php
namespace App\Models;

use CodeIgniter\Model;

class ProductVariantModel extends Model
{
    protected $table = 'product_variants';
    protected $primaryKey = 'id';
    protected $allowedFields = ['product_id','art_number','name','price','sale_currency','cost','cost_currency','weight','attributes','combination_key','image','cost_price','sale_price','vendor_price_pkr','vendor_id','vendor_price','vendor_currency','created_at','updated_at'];
    protected $useTimestamps = false;
}
