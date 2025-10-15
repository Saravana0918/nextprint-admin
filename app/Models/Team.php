<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['product_id','players', 'team_logo_url','created_by','preview_url','team_logo_url'];
    protected $casts = [
        'players' => 'array',
    ];
}
