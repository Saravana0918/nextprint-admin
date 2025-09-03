<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintArea extends Model
{
    protected $fillable = [
    'product_view_id',
    'template_id',
    'mask_svg_path',
    'left_pct','top_pct','width_pct','height_pct',
    'x_mm','y_mm','width_mm','height_mm','dpi','rotation',
    'name'
];

    protected $casts = [
        'x_mm' => 'float',
        'y_mm' => 'float',
        'width_mm' => 'float',
        'height_mm' => 'float',
        'dpi' => 'integer',
        'rotation' => 'integer',
    ];

    public function view()
    {
        return $this->belongsTo(ProductView::class, 'product_view_id');
    }
}
