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
        // table foreign key is product_id by default
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    public function shopifyProduct()
    {
        return $this->belongsTo(ShopifyProduct::class, 'shopify_product_id', 'id');
    }

        public function printMethods()
    {
        return $this->belongsToMany(
            \App\Models\PrintMethod::class,
            'product_print_method',   // pivot table name (use yours)
            'product_id',
            'print_method_id'
        )->withTimestamps();
    }
         public function methods()
    {      
        return $this->belongsToMany(
            PrintMethod::class,
            'product_print_method',   // <- pivot table name
            'product_id',
            'print_method_id'
        )->withTimestamps();
    }
}

