<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    protected $table = 'shopify_products';

    protected $fillable = [
        'shopify_product_id',   // important
        'name',                 // product title
        'handle',
        'vendor',
        'status',
        'image_url',
        'price',                // add this
        'min_price',            // keep this
        'max_price',            // optional but good
    ];

    protected $casts = [
        'price' => 'float',
        'min_price' => 'float',
        'max_price' => 'float',
    ];
}