<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('product_variants', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('product_id')->index();
        $table->string('shopify_variant_id')->nullable()->index();
        $table->string('option_name')->nullable();
        $table->string('option_value')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->string('sku')->nullable();
        $table->timestamps();

        $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
