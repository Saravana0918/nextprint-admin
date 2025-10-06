<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants'; // ✅ make sure table name correct
    protected $fillable = ['product_id','shopify_variant_id','option_value','price'];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }
}
