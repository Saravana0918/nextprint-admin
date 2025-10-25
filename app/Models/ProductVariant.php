<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    // ensure these columns exist in DB migration
    protected $fillable = [
        'product_id',
        'shopify_variant_id',
        'option_name',
        'option_value',
        'price',
        'sku'
    ];

    // timestamps true by default
    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }
}
