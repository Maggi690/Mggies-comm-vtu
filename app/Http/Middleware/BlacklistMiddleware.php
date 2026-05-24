<?php

namespace App\Http\Middleware;

use App\Models\BlacklistedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlacklistMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BlacklistedIp::where('ip_address', $request->ip())->exists()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        if (auth()->check()) {
            $user = auth()->user();

            if ($user->status === 'banned') {
                auth()->user()->tokens()->delete();
                return response()->json(['success' => false, 'message' => 'Account has been banned.'], 403);
            }

            if ($user->status === 'suspended') {
                return response()->json(['success' => false, 'message' => 'Account is suspended. Contact support.'], 403);
            }
        }

        return $next($request);
    }
}
