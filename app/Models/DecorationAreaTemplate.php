<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecorationAreaTemplate extends Model
{
        protected $fillable = [
    'name','category','width_mm','height_mm','svg_path',
    'slot_key','max_chars',   // NEW
    ];
}
