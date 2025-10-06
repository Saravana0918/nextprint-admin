<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVariantsTable extends Migration
{
    public function up()
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id')->index();
            $table->string('shopify_variant_id')->nullable()->index();
            $table->string('option1')->nullable();       // e.g. "XL"
            $table->string('option_value')->nullable();  // alt name if needed
            $table->decimal('price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('variants');
    }
}
