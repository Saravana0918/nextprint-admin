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
        Schema::create('product_print_method', function (Blueprint $table) {
            // no id column for a pivot
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('print_method_id');
            $table->timestamps();

            $table->primary(['product_id', 'print_method_id']);

            // FKs (optional for sqlite)
            // $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            // $table->foreign('print_method_id')->references('id')->on('print_methods')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
      public function down(): void
    {
        Schema::dropIfExists('product_print_method');
    }
};
