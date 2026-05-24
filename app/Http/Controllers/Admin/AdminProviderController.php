<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Providers
 * @authenticated
 */
class AdminProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|assistant_admin');
    }

    public function index(Request $request): JsonResponse
    {
        $providers = Provider::withTrashed()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->with(['services'])
            ->orderBy('priority')
            ->get();

        return response()->json(['success' => true, 'data' => $providers]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:100',
            'slug'           => 'required|string|unique:providers,slug',
            'api_key'        => 'required|string',
            'secret_key'     => 'required|string',
            'endpoint'       => 'required|url',
            'services'       => 'required|array',
            'priority'       => 'required|integer|min:1',
            'webhook_secret' => 'nullable|string',
        ]);

        $provider = Provider::create([
            'name'           => $request->name,
            'slug'           => $request->slug,
            'api_key'        => $request->api_key,      // auto-encrypted via mutator
            'secret_key'     => $request->secret_key,   // auto-encrypted via mutator
            'endpoint'       => $request->endpoint,
            'webhook_secret' => $request->webhook_secret,
            'services'       => $request->services,
            'priority'       => $request->priority,
            'status'         => 'active',
            'success_rate'   => 100,
            'failure_rate'   => 0,
        ]);

        return response()->json(['success' => true, 'message' => 'Provider added.', 'data' => $provider], 201);
    }

    public function show(int $id): JsonResponse
    {
        $provider = Provider::withTrashed()->with(['services', 'logs' => fn($q) => $q->latest()->limit(50)])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $provider]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);

        $request->validate([
            'name'       => 'sometimes|string|max:100',
            'api_key'    => 'sometimes|string',
            'secret_key' => 'sometimes|string',
            'endpoint'   => 'sometimes|url',
            'services'   => 'sometimes|array',
            'priority'   => 'sometimes|integer|min:1',
        ]);

        $provider->update($request->only(['name', 'api_key', 'secret_key', 'endpoint', 'services', 'priority', 'webhook_secret']));

        // Bust provider routing cache
        \Cache::forget("providers:*");

        return response()->json(['success' => true, 'message' => 'Provider updated.', 'data' => $provider]);
    }

    public function activate(int $id): JsonResponse
    {
        Provider::findOrFail($id)->update(['status' => 'active']);
        \Cache::flush(); // Clear all provider caches
        return response()->json(['success' => true, 'message' => 'Provider activated.']);
    }

    public function deactivate(int $id): JsonResponse
    {
        Provider::findOrFail($id)->update(['status' => 'inactive']);
        \Cache::flush();
        return response()->json(['success' => true, 'message' => 'Provider deactivated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        Provider::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Provider deleted.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'providers'          => 'required|array',
            'providers.*.id'     => 'required|integer|exists:providers,id',
            'providers.*.priority' => 'required|integer|min:1',
        ]);

        foreach ($request->providers as $item) {
            Provider::where('id', $item['id'])->update(['priority' => $item['priority']]);
        }

        \Cache::flush();
        return response()->json(['success' => true, 'message' => 'Provider order updated.']);
    }

    public function services(int $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);
        return response()->json(['success' => true, 'data' => $provider->services]);
    }

    public function addService(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'service_type' => 'required|string',
            'network'      => 'nullable|string',
            'fee_type'     => 'required|in:flat,percentage',
            'fee_value'    => 'required|numeric|min:0',
            'min_amount'   => 'nullable|numeric',
            'max_amount'   => 'nullable|numeric',
        ]);

        $service = ProviderService::create(array_merge($request->validated(), ['provider_id' => $id, 'status' => 'active']));
        return response()->json(['success' => true, 'data' => $service], 201);
    }

    public function stats(int $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);
        $logs     = $provider->logs();

        return response()->json([
            'success' => true,
            'data'    => [
                'success_rate'     => $provider->success_rate,
                'failure_rate'     => $provider->failure_rate,
                'avg_response_ms'  => $provider->avg_response_time,
                'total_logs'       => $logs->count(),
                'last_24h_success' => $logs->where('status', 'success')->where('created_at', '>=', now()->subDay())->count(),
                'last_24h_failed'  => $logs->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }
}
