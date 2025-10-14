<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_players', function (Blueprint $table) {
            $table->id();

            // Shopify order reference
            $table->string('shopify_order_id')->nullable();

            // Product relationship
            $table->unsignedBigInteger('product_id')->nullable();

            // Player details
            $table->string('name')->nullable();
            $table->string('number')->nullable();
            $table->string('size')->nullable();

            // Font & color preferences
            $table->string('font')->nullable();
            $table->string('color')->nullable();

            // Preview image or logo (optional)
            $table->string('preview_image')->nullable();

            $table->timestamps();

            // Optional foreign key (if your products table exists)
            // $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_players');
    }
};
