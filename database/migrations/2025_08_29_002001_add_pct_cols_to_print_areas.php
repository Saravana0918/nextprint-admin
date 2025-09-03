<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('print_areas', 'left_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) =>
                $t->decimal('left_pct', 8, 5)->nullable()
            );
        }

        if (!Schema::hasColumn('print_areas', 'top_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) =>
                $t->decimal('top_pct', 8, 5)->nullable()
            );
        }

        if (!Schema::hasColumn('print_areas', 'width_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) =>
                $t->decimal('width_pct', 8, 5)->nullable()
            );
        }

        if (!Schema::hasColumn('print_areas', 'height_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) =>
                $t->decimal('height_pct', 8, 5)->nullable()
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('print_areas', 'left_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) => $t->dropColumn('left_pct'));
        }
        if (Schema::hasColumn('print_areas', 'top_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) => $t->dropColumn('top_pct'));
        }
        if (Schema::hasColumn('print_areas', 'width_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) => $t->dropColumn('width_pct'));
        }
        if (Schema::hasColumn('print_areas', 'height_pct')) {
            Schema::table('print_areas', fn (Blueprint $t) => $t->dropColumn('height_pct'));
        }
    }
};
