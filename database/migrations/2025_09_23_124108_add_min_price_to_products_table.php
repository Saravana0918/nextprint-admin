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
        Schema::table('products', function (Blueprint $table) {
            // add min_price only if missing
            if (! Schema::hasColumn('products', 'min_price')) {
                // don't rely on AFTER('price') because 'price' may not exist
                $table->decimal('min_price', 10, 2)->nullable();
            }

            // ensure there is a 'price' column too (optional)
            if (! Schema::hasColumn('products', 'price')) {
                // create a 'price' column only if you want it present;
                // comment out this block if you don't want a 'price' column.
                $table->decimal('price', 10, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'min_price')) {
                $table->dropColumn('min_price');
            }
            // optional: drop price only if you added it here
            // if (Schema::hasColumn('products', 'price')) {
            //     $table->dropColumn('price');
            // }
        });
    }
};
