<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopifyPriceFieldsToProducts extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'shopify_product_id')) {
                $table->bigInteger('shopify_product_id')->unsigned()->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('products', 'min_price')) {
                $table->decimal('min_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'max_price')) {
                $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'max_price')) {
                $table->dropColumn('max_price');
            }
            if (Schema::hasColumn('products', 'min_price')) {
                $table->dropColumn('min_price');
            }
            // don't drop shopify_product_id in down to avoid accidental data loss (optional)
        });
    }
}
