<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'product_id',
        'created_by',
        'name',
        'players',
        'preview_path'
    ];

    protected $casts = [
        'players' => 'array',
    ];
}
