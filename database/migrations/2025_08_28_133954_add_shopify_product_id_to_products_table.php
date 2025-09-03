<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('products', function (Blueprint $table) {
        if (!Schema::hasColumn('products','shopify_product_id')) {
            $table->unsignedBigInteger('shopify_product_id')->nullable()->index();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        if (Schema::hasColumn('products','shopify_product_id')) {
            $table->dropColumn('shopify_product_id');
        }
    });
}
};
