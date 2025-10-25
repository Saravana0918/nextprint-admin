<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';
    protected $fillable = ['product_id','option_name','option_value','shopify_variant_id'];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }
}
