<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDesignOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('design_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('name_text')->nullable();
            $table->string('number_text')->nullable();
            $table->string('font')->nullable();
            $table->string('color')->nullable();
            $table->string('uploaded_logo_url')->nullable();
            $table->string('preview_path')->nullable(); // path to saved preview PNG
            $table->json('raw_payload')->nullable(); // store raw payload if needed
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('design_orders');
    }
}
