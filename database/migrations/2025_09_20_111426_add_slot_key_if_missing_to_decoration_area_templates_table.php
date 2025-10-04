<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('decoration_area_templates', 'slot_key')) {
            Schema::table('decoration_area_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('decoration_area_templates', 'slot_key')) {
                    $table->string('slot_key')->nullable()->after('svg_path');
                }
                if (!Schema::hasColumn('decoration_area_templates', 'max_chars')) {
                    $table->integer('max_chars')->nullable()->after('slot_key');
                }
            });
        }
    }
     public function down(): void
    {
        if (Schema::hasColumn('decoration_area_templates', 'slot_key')) {
            Schema::table('decoration_area_templates', function (Blueprint $table) {
                $table->dropColumn('slot_key');
            });
        }
    }
};
