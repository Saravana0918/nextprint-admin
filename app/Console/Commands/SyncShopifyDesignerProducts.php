<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        try {
            DB::table('products')->update(['is_in_nextprint' => 0]);
            Log::info('SYNC - reset is_in_nextprint flags to 0');
        } catch (\Exception $e) {
            Log::error('SYNC ERROR - resetting flags failed', ['error' => $e->getMessage()]);
            $this->error('Failed to reset flags: '.$e->getMessage());
            return self::FAILURE;
        }

        // 2) For each collection handle fetch products and mark them
        try {
            DB::transaction(function () use ($shopify, $handles) {
                foreach ($handles as $handle) {
                    $this->info("Fetching collection: {$handle}");

                    $items = $shopify->productsByCollectionHandle($handle);

                    // defensive: if service returns object, cast to array
                    if (is_object($items)) {
                        $items = (array) $items;
                    }


                    foreach ($items as $p) {
                        // Ensure array shape
                        if (is_object($p)) { $p = (array) $p; }

                        // Shopify GraphQL id -> numeric id
                        $id = null;
                        try {
                            $id = \App\Services\ShopifyService::gidToId($p['id'] ?? $p['product_id'] ?? null);
                        } catch (\Throwable $e) {
                            // gidToId can throw; log and continue
                            Log::warning('SYNC - gidToId failed', ['raw_id' => $p['id'] ?? null, 'error' => $e->getMessage()]);
                            $id = null;
                        }

                        if (empty($id)) {
                            Log::warning('SYNC - skipping product with empty id', ['payload' => $p]);
                            continue;
                        }

                        // image extraction (robust)
                        $img = null;

                        // REST shape: images array with 'src'
                        if (!empty($p['images']) && is_array($p['images'])) {
                            // images may be numeric array or array with edges
                            // try common REST: images[0]['src']
                            if (!empty($p['images'][0]['src'])) {
                                $img = $p['images'][0]['src'];
                            } elseif (!empty($p['images'][0]['url'])) {
                                $img = $p['images'][0]['url'];
                            } elseif (!empty($p['images'][0]['node']['url'])) {
                                $img = $p['images'][0]['node']['url'];
                            }
                        }

                        // GraphQL shape: images.edges[0].node.url
                        if (empty($img) && !empty($p['images']['edges'][0]['node']['url'])) {
                            $img = $p['images']['edges'][0]['node']['url'];
                        }

                        // fallback single image
                        if (empty($img) && !empty($p['image']['src'])) {
                            $img = $p['image']['src'];
                        }
                        if (empty($img) && !empty($p['image']['url'])) {
                            $img = $p['image']['url'];
                        }

                        $min = $p['priceRangeV2']['minVariantPrice']['amount'] ?? null;


                        // upsert shopify_products table
                        // Keep the shopify_products.id as Shopify product id (if that's your design).
                        DB::table('shopify_products')->updateOrInsert(
                            ['id' => $id],
                            [
                                'title'      => $p['title'] ?? ($p['name'] ?? null),
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
                                'name'            => $p['title'] ?? ($p['name'] ?? null),
                                'updated_at'      => now(),
                                'created_at'      => now(),
                                'is_in_nextprint' => 1,
                                'image_url'       => $img,
                            ]
                        );

                    } // foreach products
                } // foreach collections
            }); // transaction
        } catch (\Exception $e) {
            Log::error('SYNC ERROR - transaction failed', ['error' => $e->getMessage()]);
            $this->error('Sync transaction failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Done.');
        Log::info('SYNC - completed successfully for handles: '.$handles->join(', '));
        return self::SUCCESS;
    }
}
