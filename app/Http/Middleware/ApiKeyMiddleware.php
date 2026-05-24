<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\BlacklistedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $publicKey  = $request->header('X-API-Key');
        $secretKey  = $request->header('X-API-Secret');

        if (!$publicKey || !$secretKey) {
            return response()->json([
                'success' => false,
                'message' => 'API credentials required. Include X-API-Key and X-API-Secret headers.',
            ], 401);
        }

        $apiKey = ApiKey::where('public_key', $publicKey)->where('status', 'active')->first();

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Invalid API key.'], 401);
        }

        // Verify secret
        if (!hash_equals($apiKey->secret_key, hash('sha256', $secretKey))) {
            return response()->json(['success' => false, 'message' => 'Invalid API secret.'], 401);
        }

        // Check IP whitelist
        if (!$apiKey->isIpAllowed($request->ip())) {
            return response()->json(['success' => false, 'message' => 'Request from unauthorized IP address.'], 403);
        }

        // Check global blacklist
        if (BlacklistedIp::where('ip_address', $request->ip())->exists()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        // Check daily limit
        if ($apiKey->daily_limit && $apiKey->daily_usage >= $apiKey->daily_limit) {
            return response()->json(['success' => false, 'message' => 'Daily API limit exceeded.'], 429);
        }

        // Attach to request
        $request->attributes->set('api_key', $apiKey);

        // Update usage stats
        $apiKey->update([
            'last_used_at' => now(),
            'daily_usage'  => \DB::raw('daily_usage + 1'),
            'monthly_usage'=> \DB::raw('monthly_usage + 1'),
        ]);

        // Log API call
        \App\Models\ApiKeyLog::create([
            'api_key_id' => $apiKey->id,
            'endpoint'   => $request->path(),
            'method'     => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status'     => 'processed',
        ]);

        return $next($request);
    }
}
