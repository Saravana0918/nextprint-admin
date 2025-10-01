<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    // Table name (plural)
    protected $table = 'shopify_products';

    protected $fillable = [
        'title', 'handle', 'vendor', 'status',
        'image_url', 'min_price'
    ];
}
