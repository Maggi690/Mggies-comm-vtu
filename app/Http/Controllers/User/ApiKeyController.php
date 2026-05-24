<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Developer API Keys
 * @authenticated
 */
class ApiKeyController extends Controller
{
    /**
     * List API keys
     */
    public function index(Request $request): JsonResponse
    {
        $keys = ApiKey::where('user_id', $request->user()->id)->get()->map(function ($key) {
            return [
                'id'               => $key->id,
                'name'             => $key->name,
                'public_key'       => $key->public_key,
                'status'           => $key->status,
                'ip_whitelist'     => $key->ip_whitelist,
                'allowed_services' => $key->allowed_services,
                'daily_limit'      => $key->daily_limit,
                'monthly_limit'    => $key->monthly_limit,
                'webhook_url'      => $key->webhook_url,
                'last_used_at'     => $key->last_used_at,
                'created_at'       => $key->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $keys]);
    }

    /**
     * Create new API key pair
     *
     * @bodyParam name string required Key name/label. Example: My App
     * @bodyParam ip_whitelist array optional Allowed IP addresses.
     * @bodyParam allowed_services array optional Allowed service types.
     * @bodyParam webhook_url string optional Webhook URL for notifications.
     * @bodyParam daily_limit integer optional Daily request limit.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->api_access_enabled) {
            return response()->json(['success' => false, 'message' => 'API access not enabled for your account. Contact support.'], 403);
        }

        $request->validate([
            'name'             => 'required|string|max:100',
            'ip_whitelist'     => 'nullable|array',
            'ip_whitelist.*'   => 'ip',
            'allowed_services' => 'nullable|array',
            'allowed_services.*' => 'in:airtime,data,cable,electricity,exam',
            'webhook_url'      => 'nullable|url',
            'daily_limit'      => 'nullable|integer|min:1|max:10000',
        ]);

        $secretKey = Str::random(64);

        $apiKey = ApiKey::create([
            'user_id'          => $request->user()->id,
            'name'             => $request->name,
            'public_key'       => 'pk_' . Str::random(32),
            'secret_key'       => hash('sha256', $secretKey), // Store hash, return plain once
            'ip_whitelist'     => $request->ip_whitelist ?? [],
            'allowed_services' => $request->allowed_services ?? ['airtime', 'data', 'cable', 'electricity', 'exam'],
            'webhook_url'      => $request->webhook_url,
            'webhook_secret'   => Str::random(32),
            'daily_limit'      => $request->daily_limit ?? 100,
            'monthly_limit'    => ($request->daily_limit ?? 100) * 30,
            'status'           => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key created. Store the secret key safely — it will not be shown again.',
            'data'    => [
                'id'             => $apiKey->id,
                'name'           => $apiKey->name,
                'public_key'     => $apiKey->public_key,
                'secret_key'     => $secretKey,  // Only returned once
                'webhook_secret' => $apiKey->webhook_secret,
            ],
        ], 201);
    }

    /**
     * Revoke an API key
     *
     * @urlParam id integer required API key ID. Example: 1
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $apiKey->update(['status' => 'revoked']);

        return response()->json(['success' => true, 'message' => 'API key revoked.']);
    }

    /**
     * Update API key settings
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'name'             => 'sometimes|string|max:100',
            'ip_whitelist'     => 'nullable|array',
            'ip_whitelist.*'   => 'ip',
            'allowed_services' => 'nullable|array',
            'webhook_url'      => 'nullable|url',
        ]);

        $apiKey->update($request->only(['name', 'ip_whitelist', 'allowed_services', 'webhook_url']));

        return response()->json(['success' => true, 'message' => 'API key updated.', 'data' => $apiKey]);
    }

    /**
     * API usage stats for a key
     */
    public function usage(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $stats = \App\Models\Transaction::where('api_key_id', $apiKey->id)
            ->select(
                \DB::raw('COUNT(*) as total'),
                \DB::raw('SUM(CASE WHEN status="successful" THEN 1 ELSE 0 END) as successful'),
                \DB::raw('SUM(amount) as total_amount'),
                'service_type',
            )
            ->groupBy('service_type')
            ->get();

        return response()->json(['success' => true, 'data' => ['stats' => $stats, 'key' => $apiKey->only(['daily_usage', 'monthly_usage', 'daily_limit', 'monthly_limit'])]]);
    }
}
