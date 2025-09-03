<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('product_views', function (Blueprint $table) {
            // view upload இல்லாதபோது Shopify/Product image fallback வைத்துக்க
            $table->string('bg_image_url')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('product_views', function (Blueprint $table) {
            $table->dropColumn('bg_image_url');
        });
    }
};
