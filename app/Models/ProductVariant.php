<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'variants'; // <--- IMPORTANT: match controller/schema checks
    protected $fillable = ['product_id','shopify_variant_id','option_value','price',
                           // add any other variant columns like 'option1','sku','price_cents' etc
                          ];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id', 'id');
    }
}
