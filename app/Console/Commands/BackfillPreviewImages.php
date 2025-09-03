<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackfillPreviewImages extends Command
{
    protected $signature = 'nextprint:backfill-previews {--dry : Show what would change, don\'t write}';
    protected $description = 'Backfill preview images and normalize paths for products/product_views';

    public function handle(): int
    {
        $dry = $this->option('dry');
        $this->info('== Backfill Previews '.($dry ? '(DRY RUN)' : '').' ==');

        // 1) products.thumbnail <= shopify_products.image_url (only if empty)
        $this->line('Step 1: Copy Shopify image → products.thumbnail (when empty)…');
        $rows = DB::table('products as p')
            ->leftJoin('shopify_products as sp', 'sp.id', '=', 'p.shopify_product_id')
            ->where(function ($q) {
                $q->whereNull('p.thumbnail')->orWhere('p.thumbnail', '');
            })
            ->whereNotNull('sp.image_url')
            ->select('p.id', 'sp.image_url')
            ->get();

        $count1 = 0;
        foreach ($rows as $r) {
            $this->line(" - product {$r->id} ← {$r->image_url}");
            if (!$dry) {
                DB::table('products')->where('id', $r->id)->update(['thumbnail' => $r->image_url]);
            }
            $count1++;
        }
        $this->info("Done: {$count1} thumbnails backfilled.");

        // 2) Normalize product_views.image_path (slashes, strip storage/public prefixes)
        $this->line('Step 2: Normalize product_views.image_path…');
        $views = DB::table('product_views')
            ->select('id', 'image_path')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->get();

        $count2 = 0;
        foreach ($views as $v) {
            $raw = $v->image_path;

            $norm = str_replace('\\', '/', $raw);                 // Windows → POSIX
            $norm = preg_replace('~^https?://[^/]+/storage/~i', '', $norm); // in case a full url was stored
            $norm = preg_replace('~^/?storage/~', '', $norm);     // strip leading storage/
            $norm = preg_replace('~^/?public/~',  '', $norm);     // strip leading public/
            $norm = ltrim($norm, '/');

            if ($norm !== $raw) {
                $this->line(" - view {$v->id}: '{$raw}' → '{$norm}'");
                if (!$dry) {
                    DB::table('product_views')->where('id', $v->id)->update(['image_path' => $norm]);
                }
                $count2++;
            }
        }
        $this->info("Done: {$count2} view paths normalized.");

        // 3) Quick sanity: print final preview URLs like controller does
        $this->line('Sample previews after fix:');
        $sample = DB::table('products as p')
            ->leftJoin('shopify_products as sp', 'sp.id', '=', 'p.shopify_product_id')
            ->leftJoin(DB::raw("
                (
                  SELECT v1.*
                  FROM product_views v1
                  JOIN (
                      SELECT product_id, MIN(id) AS id
                      FROM product_views
                      GROUP BY product_id
                  ) x ON x.product_id = v1.product_id AND x.id = v1.id
                ) pv
            "), 'pv.product_id', '=', 'p.id')
            ->select([
                'p.id',
                DB::raw("COALESCE(pv.image_path, sp.image_url, p.thumbnail) as preview_image")
            ])
            ->orderBy('p.id')->limit(10)->get();

        foreach ($sample as $r) {
            $src = $r->preview_image;
            if ($src && !preg_match('~^https?://~i', $src)) {
                $src = Storage::disk('public')->url(ltrim(preg_replace('~^/?storage/~','',$src),'/'));
            }
            $this->line(" - product {$r->id} → {$src}");
        }

        $this->info('All done ✅');
        return self::SUCCESS;
    }
}
