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
        if (! Schema::hasColumn('decoration_area_templates', 'slot_key')) {
            Schema::table('decoration_area_templates', function (Blueprint $table) {
                $table->string('slot_key')->nullable()->after('svg_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
     public function down(): void
    {
        if (Schema::hasColumn('decoration_area_templates', 'slot_key')) {
            Schema::table('decoration_area_templates', function (Blueprint $table) {
                $table->dropColumn('slot_key');
            });
        }
    }
};
