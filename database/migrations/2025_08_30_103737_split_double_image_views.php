<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void {
        $views = DB::table('product_views')->get();

        foreach ($views as $view) {
            // Detect double image (your logic may vary)
            if (str_contains(strtolower($view->image_path), 'double')) {
                $base = pathinfo($view->image_path, PATHINFO_FILENAME);
                $ext  = pathinfo($view->image_path, PATHINFO_EXTENSION);

                $front = $base . '_front.' . $ext;
                $back  = $base . '_back.'  . $ext;

                // 👇 insert front
                DB::table('product_views')->insert([
                    'product_id'   => $view->product_id,
                    'name'         => 'Front',
                    'image_path'   => "images/$front",
                    'bg_image_url' => "images/$front",
                    'dpi'          => $view->dpi ?? 300,
                ]);
                // 👇 insert back
                DB::table('product_views')->insert([
                    'product_id'   => $view->product_id,
                    'name'         => 'Back',
                    'image_path'   => "images/$back",
                    'bg_image_url' => "images/$back",
                    'dpi'          => $view->dpi ?? 300,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
   public function down(): void {
        DB::table('product_views')
            ->whereIn('name', ['Front','Back'])
            ->delete();
    }
};
