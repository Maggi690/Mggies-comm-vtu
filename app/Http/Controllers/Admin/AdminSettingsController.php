<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @group Admin - Settings
 * @authenticated
 */
class AdminSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(): JsonResponse
    {
        $settings = \DB::table('settings')->pluck('value', 'key');
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings'       => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($request->settings as $setting) {
            \DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'updated_at' => now()],
            );
        }

        Cache::forget('app_settings');

        return response()->json(['success' => true, 'message' => 'Settings updated.']);
    }

    public function dataPlanRates(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $plans = \App\Models\DataPlan::orderBy('network')->orderBy('amount')->get();
            return response()->json(['success' => true, 'data' => $plans]);
        }

        $request->validate([
            'plans'               => 'required|array',
            'plans.*.id'          => 'required|integer|exists:data_plans,id',
            'plans.*.selling_price' => 'required|numeric|min:0',
            'plans.*.status'      => 'required|in:active,inactive',
        ]);

        foreach ($request->plans as $plan) {
            \App\Models\DataPlan::where('id', $plan['id'])->update([
                'selling_price' => $plan['selling_price'],
                'status'        => $plan['status'],
            ]);
        }

        Cache::forget('data_plans');

        return response()->json(['success' => true, 'message' => 'Data plan rates updated.']);
    }
}
