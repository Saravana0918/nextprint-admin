<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ShopifyService;

class SyncShopifyDesignerProducts extends Command
{
    protected $signature = 'shopify:sync-designer';
    protected $description = 'Sync products from Shopify collection(s) into local DB';

    public function handle(ShopifyService $shopify)
    {
        $handles = collect(explode(',', env('DESIGNER_COLLECTION_HANDLES','show-in-nextprint')))
            ->map(fn($h)=>trim($h))->filter();

        if ($handles->isEmpty()) {
            $this->error('No collection handles configured.');
            return self::FAILURE;
        }

        $this->info('Syncing: '.$handles->join(', '));

        DB::transaction(function () use ($shopify, $handles) {
            foreach ($handles as $handle) {
                foreach ($shopify->productsByCollectionHandle($handle) as $p) {
    // REST product id is numeric
    $id = $p['id'] ?? null;

    // image (REST: images[0].src)
    $img = $p['images'][0]['src'] ?? null;

    // variants first price as min (approx)
    $min = $p['variants'][0]['price'] ?? null;

    // tags: REST returns comma-separated string -> array
    $tagsArr = [];
    if (!empty($p['tags'])) {
        $tagsArr = array_map('trim', explode(',', $p['tags']));
    }

    DB::table('shopify_products')->updateOrInsert(
        ['id' => $id],
        [
            'title'      => $p['title'] ?? null,
            'handle'     => $p['handle'] ?? null,
            'vendor'     => $p['vendor'] ?? null,
            'status'     => $p['status'] ?? null,
            'image_url'  => $img,
            'min_price'  => $min,
            'tags'       => json_encode($tagsArr),
            'updated_at' => now(),
            'created_at' => now()
        ]
    );

    DB::table('products')->updateOrInsert(
        ['shopify_product_id' => $id],
        [
            'name'       => $p['title'] ?? null,
            'updated_at' => now(),
            'created_at' => now()
        ]
    );
}

            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }
}
