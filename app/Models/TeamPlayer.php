<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopify_order_id',
        'product_id',
        'name',
        'number',
        'size',
        'font',
        'color',
        'preview_image',
    ];
}
