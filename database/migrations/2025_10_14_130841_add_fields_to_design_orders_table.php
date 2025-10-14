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
    Schema::table('design_orders', function (Blueprint $table) {
        // add columns if not present already - adjust types as you prefer
        if (!Schema::hasColumn('design_orders', 'shopify_order_id')) {
            $table->string('shopify_order_id')->nullable()->after('id');
        }
        if (!Schema::hasColumn('design_orders', 'shopify_line_item_id')) {
            $table->string('shopify_line_item_id')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'product_id')) {
            $table->unsignedBigInteger('product_id')->nullable()->index();
        }
        if (!Schema::hasColumn('design_orders', 'variant_id')) {
            $table->string('variant_id')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'customer_name')) {
            $table->string('customer_name')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'customer_number')) {
            $table->string('customer_number')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'font')) {
            $table->string('font')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'color')) {
            $table->string('color', 20)->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'preview_src')) {
            $table->string('preview_src')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'download_url')) {
            $table->string('download_url')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'payload')) {
            $table->longText('payload')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'status')) {
            $table->string('status')->default('new');
        }
        if (!Schema::hasColumn('design_orders', 'created_at')) {
            $table->timestamp('created_at')->nullable();
        }
        if (!Schema::hasColumn('design_orders', 'updated_at')) {
            $table->timestamp('updated_at')->nullable();
        }
    });
}

public function down()
{
    Schema::table('design_orders', function (Blueprint $table) {
        $cols = [
          'shopify_order_id','shopify_line_item_id','product_id','variant_id',
          'customer_name','customer_number','font','color','preview_src',
          'download_url','payload','status'
        ];
        foreach ($cols as $c) {
            if (Schema::hasColumn('design_orders', $c)) {
                $table->dropColumn($c);
            }
        }
    });
}

};
