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

    // 1) Reset all local flags first
    DB::table('products')->update(['is_in_nextprint' => 0]);

    // 2) For each collection handle fetch products and mark them
    DB::transaction(function () use ($shopify, $handles) {
        foreach ($handles as $handle) {
            $this->info("Fetching collection: {$handle}");
            $items = $shopify->productsByCollectionHandle($handle);

            foreach ($items as $p) {
                $id = \App\Services\ShopifyService::gidToId($p['id']);

                // image extraction (robust)
                $img = null;
                if (!empty($p['images']) && !empty($p['images'][0]['src'])) {
                    $img = $p['images'][0]['src'];
                }
                if (empty($img) && !empty($p['images']['edges'][0]['node']['url'])) {
                    $img = $p['images']['edges'][0]['node']['url'];
                }
                if (empty($img) && !empty($p['image']['src'])) {
                    $img = $p['image']['src'];
                }

                $min = $p['priceRangeV2']['minVariantPrice']['amount'] ?? null;

                // upsert shopify_products table
                DB::table('shopify_products')->updateOrInsert(
                    ['id' => $id],
                    [
                        'title'      => $p['title'] ?? null,
                        'handle'     => $p['handle'] ?? null,
                        'vendor'     => $p['vendor'] ?? null,
                        'status'     => $p['status'] ?? null,
                        'image_url'  => $img,
                        'min_price'  => $min,
                        'tags'       => json_encode($p['tags'] ?? []),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                // upsert products and mark as in NextPrint
                DB::table('products')->updateOrInsert(
                    ['shopify_product_id' => $id],
                    [
                        'name'            => $p['title'] ?? null,
                        'updated_at'      => now(),
                        'created_at'      => now(),
                        'is_in_nextprint' => 1,
                        'image_url'       => $img,
                    ]
                );
            } // foreach products
        } // foreach collections
    });

    $this->info('Done.');
    return self::SUCCESS;
}
}
