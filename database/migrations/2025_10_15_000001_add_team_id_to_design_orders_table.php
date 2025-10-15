<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('design_orders', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('design_orders', function (Blueprint $table) {
            if (Schema::hasColumn('design_orders', 'team_id')) {
                $table->dropColumn('team_id');
            }
        });
    }
};
