<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImprintnextMinimalSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $pid = DB::table('products')->insertGetId([
                'name' => 'Demo T-Shirt',
                'sku' => 'DEMO-TEE',
                'thumbnail' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $vid = DB::table('product_views')->insertGetId([
                'product_id' => $pid,
                'name' => 'Front',
                'position' => 1,
                'image_path' => '',
                'thumbnail' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('print_areas')->insert([
                'product_view_id' => $vid,
                'name' => 'Front Area',
                'width_mm' => 300,
                'height_mm' => 400,
                'x_mm' => 50,
                'y_mm' => 80,
                'dpi' => 300,
                'rotation' => 0,
                'mask_svg_path' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
