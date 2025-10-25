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
        $table->id();
        $table->unsignedBigInteger('product_id')->nullable()->index();
        $table->string('option_name')->nullable();
        $table->string('option_value')->nullable();
        $table->string('shopify_variant_id')->nullable()->unique();
        $table->timestamps();
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
