<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopify_products', function (Blueprint $table) {
            if (!Schema::hasColumn('shopify_products', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('shopify_products', 'min_price')) {
                $table->decimal('min_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('shopify_products', 'max_price')) {
                $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shopify_products', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_products', 'max_price')) {
                $table->dropColumn('max_price');
            }
            if (Schema::hasColumn('shopify_products', 'min_price')) {
                $table->dropColumn('min_price');
            }
            if (Schema::hasColumn('shopify_products', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
