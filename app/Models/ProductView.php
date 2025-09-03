<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductView extends Model {
  protected $fillable = ['product_id','name','position','image_path','thumbnail','bg_image_url'];
  public function product(){ return $this->belongsTo(Product::class); }
  public function areas(){ return $this->hasMany(PrintArea::class,'product_view_id'); }
}


