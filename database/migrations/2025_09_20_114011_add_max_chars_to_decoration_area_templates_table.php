<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('decoration_area_templates', 'max_chars')) {
            Schema::table('decoration_area_templates', function (Blueprint $table) {
                $table->integer('max_chars')->nullable()->after('slot_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('decoration_area_templates', 'max_chars')) {
            Schema::table('decoration_area_templates', function (Blueprint $table) {
                $table->dropColumn('max_chars');
            });
        }
    }
};
