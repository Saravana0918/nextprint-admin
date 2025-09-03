<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'icon_url',
        'description',
        'status',
        'sort_order',
        'settings'
    ];

   public function products()
    {
        return $this->belongsToMany(
            \App\Models\Product::class,
            'product_print_method',
            'print_method_id',
            'product_id'
        )->withTimestamps();
    }


    protected $casts = [
        'settings' => 'array',
    ];
}
