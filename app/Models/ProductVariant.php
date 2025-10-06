<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'variants';
    protected $fillable = ['product_id','shopify_variant_id','option1','option_value','price','sku','quantity'];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id', 'id');
    }
}
