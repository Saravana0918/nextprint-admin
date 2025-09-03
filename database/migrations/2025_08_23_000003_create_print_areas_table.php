<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('print_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_view_id')->constrained('product_views')->onDelete('cascade');
            $table->string('name');
            $table->integer('width_mm')->default(0);
            $table->integer('height_mm')->default(0);
            $table->integer('x_mm')->default(0);
            $table->integer('y_mm')->default(0);
            $table->integer('dpi')->default(300);
            $table->integer('rotation')->default(0);
            $table->string('mask_svg_path')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('print_areas');
    }
};
