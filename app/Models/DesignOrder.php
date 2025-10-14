<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignOrder extends Model
{
    protected $table = 'design_orders';

    protected $fillable = [
        'product_id','shopify_product_id','variant_id','size','quantity',
        'name_text','number_text','font','color','uploaded_logo_url','preview_path','raw_payload'
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];
}
