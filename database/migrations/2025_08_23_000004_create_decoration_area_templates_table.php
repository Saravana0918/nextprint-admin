<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('decoration_area_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['regular','custom','without_bleed'])->default('custom');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->string('svg_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decoration_area_templates');
    }
};
