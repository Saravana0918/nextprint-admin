<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ShopifySyncVariants extends Command
{
    protected $signature = 'shopify:sync-variants 
                            {shopify_product_id? : Shopify product id (optional)} 
                            {--all : Sync all products stored in local products table}';
    protected $description = 'Sync variants from Shopify Admin API into product_variants table (upsert).';

    public function handle()
    {
        $domain = env('SHOPIFY_ADMIN_DOMAIN');
        $version = env('SHOPIFY_ADMIN_API_VERSION', '2024-10');
        $token = env('SHOPIFY_ADMIN_ACCESS_TOKEN');

        if (!$domain || !$token) {
            $this->error('Set SHOPIFY_ADMIN_DOMAIN and SHOPIFY_ADMIN_ACCESS_TOKEN in .env');
            return 1;
        }

        $productId = $this->argument('shopify_product_id');
        $doAll = $this->option('all');

        $toSync = [];

        if ($doAll) {
            $this->info('Loading local products to sync...');
            $rows = DB::table('products')->select('shopify_product_id')->whereNotNull('shopify_product_id')->get();
            foreach ($rows as $r) {
                if (!empty($r->shopify_product_id)) $toSync[] = $r->shopify_product_id;
            }
        } elseif ($productId) {
            $toSync[] = $productId;
        } else {
            $this->error('Provide shopify_product_id or use --all');
            return 1;
        }

        foreach ($toSync as $spid) {
            $this->line("Syncing Shopify product id: {$spid} ...");
            try {
                $url = "https://{$domain}/admin/api/{$version}/products/{$spid}.json";
                $resp = Http::withToken($token)->acceptJson()->get($url);

                if (!$resp->ok()) {
                    $this->error("Shopify API error for {$spid}: HTTP {$resp->status()}");
                    $this->line($resp->body());
                    continue;
                }

                $payload = $resp->json();
                $shopifyProduct = $payload['product'] ?? null;
                if (!$shopifyProduct) {
                    $this->error("No product data returned for {$spid}");
                    continue;
                }

                $variants = $shopifyProduct['variants'] ?? [];
                $options = $shopifyProduct['options'] ?? [];

                // detect which option is "Size"
                $sizeOptionName = null;
                foreach ($options as $opt) {
                    if (!empty($opt['name']) && Str::lower($opt['name']) === 'size') {
                        $sizeOptionName = $opt['name'];
                        break;
                    }
                }
                if (!$sizeOptionName && !empty($options[0]['name'])) $sizeOptionName = $options[0]['name'];

                $rowsToUpsert = [];
                $now = Carbon::now()->toDateTimeString();

                foreach ($variants as $v) {
                    // determine appropriate option value
                    $optValue = null;
                    $optionIndex = null;
                    if ($sizeOptionName && !empty($options)) {
                        foreach ($options as $idx => $opt) {
                            if ($opt['name'] === $sizeOptionName) { $optionIndex = $idx; break; }
                        }
                    }
                    if ($optionIndex !== null) {
                        $key = 'option' . ($optionIndex + 1);
                        $optValue = $v[$key] ?? null;
                    }
                    if (!$optValue) $optValue = $v['option1'] ?? null;

                    $rowsToUpsert[] = [
                        'product_id' => null,
                        'option_name' => $sizeOptionName ?? 'Size',
                        'option_value' => (string)($optValue ?? ''),
                        'shopify_variant_id' => (string)($v['id'] ?? ''),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // find local product id by shopify_product_id
                $localProduct = DB::table('products')->where('shopify_product_id', $spid)->first();
                $localProductId = $localProduct->id ?? null;
                foreach ($rowsToUpsert as &$r) $r['product_id'] = $localProductId;
                unset($r);

                if (empty($rowsToUpsert)) {
                    $this->line("No variants for {$spid}");
                    continue;
                }

                DB::table('product_variants')->upsert(
                    $rowsToUpsert,
                    ['shopify_variant_id'],
                    ['product_id','option_name','option_value','updated_at']
                );

                $this->info("Synced " . count($rowsToUpsert) . " variants for product {$spid}");
            } catch (\Throwable $e) {
                $this->error("Exception syncing {$spid}: " . $e->getMessage());
                continue;
            }
        }

        $this->info('Done.');
        return 0;
    }
}
