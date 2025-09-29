<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
   protected $fillable = ['product_id','players','created_by'];
    protected $casts = [
  'players' => 'array',
];
}
