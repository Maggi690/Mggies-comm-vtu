<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::with('user:id,first_name,last_name,email,phone,user_type')
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) => $u
                ->where('email', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")))
            ->when($request->min_balance, fn($q) => $q->where('balance', '>=', $request->min_balance))
            ->when($request->max_balance, fn($q) => $q->where('balance', '<=', $request->max_balance))
            ->orderByDesc('balance')
            ->paginate($request->per_page ?? 25);

        $summary = [
            'total_balance'  => Wallet::sum('balance'),
            'total_wallets'  => Wallet::count(),
            'frozen_balance' => Wallet::sum('frozen_balance'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => $wallets,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $wallet = Wallet::with(['user', 'transactions' => fn($q) => $q->latest()->limit(20)])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $wallet]);
    }
}
