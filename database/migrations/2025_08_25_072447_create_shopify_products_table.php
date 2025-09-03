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
    Schema::create('shopify_products', function (Blueprint $t) {
        $t->unsignedBigInteger('id')->primary();
        $t->string('title');
        $t->string('handle')->index();
        $t->string('vendor')->nullable();
        $t->string('status')->nullable();
        $t->string('image_url')->nullable();
        $t->decimal('min_price',10,2)->nullable();
        $t->json('tags')->nullable();
        $t->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_products');
    }
};
