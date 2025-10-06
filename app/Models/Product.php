<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name','sku','thumbnail','shopify_product_id'];

    public function views()
    {
        return $this->hasMany(ProductView::class);
    }

    public function variants()
    {
        return $this->hasMany(\App\Models\ProductVariant::class, 'product_id', 'id');
    }

    public function shopifyProduct()
    {
        return $this->belongsTo(ShopifyProduct::class, 'shopify_product_id', 'id');
    }

    public function printMethods()
    {
        return $this->belongsToMany(
            \App\Models\PrintMethod::class,
            'product_print_method',
            'product_id',
            'print_method_id'
        )->withTimestamps();
    }

    public function methods()
    {
        return $this->belongsToMany(
            PrintMethod::class,
            'product_print_method',
            'product_id',
            'print_method_id'
        )->withTimestamps();
    }
}
