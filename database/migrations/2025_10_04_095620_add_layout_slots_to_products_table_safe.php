<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // If DB supports JSON natively (MySQL >= 5.7.8), we'll add JSON column,
        // otherwise fallback to TEXT for compatibility.
        $supportsJson = false;

        try {
            $ver = DB::selectOne('select version() as v')->v ?? '';
            // crude check for mysql >= 5.7.8
            if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $ver, $m)) {
                $major = (int)$m[1]; $minor = (int)$m[2]; $patch = (int)$m[3];
                if ($major > 5 || ($major === 5 && $minor > 7) || ($major === 5 && $minor === 7 && $patch >= 8)) {
                    $supportsJson = true;
                } elseif ($major >= 8) {
                    $supportsJson = true;
                }
            }
        } catch (\Throwable $e) {
            // if check fails, default to text to be safe
            $supportsJson = false;
        }

        Schema::table('products', function (Blueprint $table) use ($supportsJson) {
            if (!Schema::hasColumn('products', 'layout_slots')) {
                if ($supportsJson) {
                    $table->json('layout_slots')->nullable();
                } else {
                    $table->text('layout_slots')->nullable();
                }
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'layout_slots')) {
                $table->dropColumn('layout_slots');
            }
        });
    }
};
