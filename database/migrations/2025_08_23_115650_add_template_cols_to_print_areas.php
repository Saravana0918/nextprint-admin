<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('print_areas', function (Blueprint $table) {
            if (!Schema::hasColumn('print_areas','label')) {
                $table->string('label')->nullable();
            }
            if (!Schema::hasColumn('print_areas','mask_svg_path')) {
                $table->string('mask_svg_path')->nullable();
            }
            if (!Schema::hasColumn('print_areas','template_id')) {
                $table->foreignId('template_id')
                      ->nullable()
                      ->constrained('decoration_area_templates')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('print_areas', function (Blueprint $table) {
            if (Schema::hasColumn('print_areas','template_id')) {
                $table->dropConstrainedForeignId('template_id');
            }
            if (Schema::hasColumn('print_areas','mask_svg_path')) {
                $table->dropColumn('mask_svg_path');
            }
            if (Schema::hasColumn('print_areas','label')) {
                $table->dropColumn('label');
            }
        });
    }
};
