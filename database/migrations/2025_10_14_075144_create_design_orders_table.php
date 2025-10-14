<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('design_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shopify_order_id')->nullable()->index();
            $table->string('shopify_line_item_id')->nullable()->index();
            $table->string('product_id')->nullable()->index();
            $table->string('variant_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('font')->nullable();
            $table->string('color')->nullable();
            $table->string('preview_src')->nullable(); // /storage/...
            $table->text('download_url')->nullable(); // the design-download URL from Additional details
            $table->json('payload')->nullable(); // raw JSON we fetched from download_url (if any)
            $table->string('status')->default('new');
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('design_orders');
    }
};
