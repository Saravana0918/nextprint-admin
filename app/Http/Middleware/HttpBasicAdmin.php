<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HttpBasicAdmin
{
    /**
     * Handle an incoming request.
     * Uses simple HTTP Basic against ADMIN_USER / ADMIN_PASS from .env
     */
    public function handle(Request $request, Closure $next)
    {
        $expectedUser = env('ADMIN_USER', null);
        $expectedPass = env('ADMIN_PASS', null);

        // If no credentials configured, deny access (fail safe)
        if (!$expectedUser || !$expectedPass) {
            abort(503, 'Admin authentication not configured.');
        }

        // Try PHP HTTP Basic
        $user = $request->server('PHP_AUTH_USER') ?? $request->getUser();
        $pass = $request->server('PHP_AUTH_PW') ?? $request->getPassword();

        // If not present, send 401 header to trigger browser prompt
        if (!$user || !$pass) {
            return response('Authentication required', 401)
                ->header('WWW-Authenticate', 'Basic realm="Admin Area"');
        }

        // constant-time compare
        if (!hash_equals($expectedUser, (string)$user) || !hash_equals($expectedPass, (string)$pass)) {
            return response('Invalid credentials', 401)
                ->header('WWW-Authenticate', 'Basic realm="Admin Area"');
        }

        // allowed
        return $next($request);
    }
}
