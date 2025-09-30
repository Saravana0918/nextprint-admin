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
            if (!Schema::hasColumn('products', 'min_price')) {
                $table->decimal('min_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'max_price')) {
                $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
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
            if (Schema::hasColumn('products', 'max_price')) {
                $table->dropColumn('max_price');
            }
        });
    }
};
