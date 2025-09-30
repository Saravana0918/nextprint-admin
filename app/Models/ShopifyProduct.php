<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    protected $table = 'shopify_products';

    protected $fillable = [
        'shopify_product_id',  
        'name',                
        'handle',
        'vendor',
        'status',
        'image_url',
        'price',              
        'min_price',          
        'max_price',            
    ];

    protected $casts = [
        'price' => 'float',
        'min_price' => 'float',
        'max_price' => 'float',
    ];
}