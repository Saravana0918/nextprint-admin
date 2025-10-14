<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DesignOrder extends Model
{
    protected $fillable = [
        'shopify_order_id','shopify_line_item_id','product_id','variant_id',
        'customer_name','customer_number','font','color','preview_src','download_url','payload','status'
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
