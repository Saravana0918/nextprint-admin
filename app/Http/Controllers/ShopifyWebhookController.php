<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        \Log::info('Webhook reached', [
        'topic' => $request->header('X-Shopify-Topic'),
        'hmac'  => $request->header('X-Shopify-Hmac-Sha256'),
        ]);
        // Verify HMAC
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $topic = $request->header('X-Shopify-Topic');

        $calc = base64_encode(
            hash_hmac('sha256', $request->getContent(), env('SHOPIFY_API_SECRET'), true)
        );

        if (!hash_equals($calc, $hmac)) {
            return response('Invalid HMAC', 401);
        }

        $topics = ['products/create','products/update','products/delete','collections/create','collections/update'];
            if (in_array($topic, $topics)) {
                app(\App\Services\ShopifyService::class)->syncNextprintToLocal();
            }

        return response('ok', 200);
    }
}
