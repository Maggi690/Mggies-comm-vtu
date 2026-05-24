<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user()->load('wallet');
        $userId    = $user->id;
        $thisMonth = now()->startOfMonth();

        $stats = [
            'wallet_balance'    => $user->wallet?->balance ?? 0,
            'available_balance' => $user->wallet?->available_balance ?? 0,
            'transactions'      => [
                'total'           => Transaction::where('user_id', $userId)->count(),
                'this_month'      => Transaction::where('user_id', $userId)->where('created_at', '>=', $thisMonth)->count(),
                'successful'      => Transaction::where('user_id', $userId)->where('status', 'successful')->count(),
                'total_spent'     => Transaction::where('user_id', $userId)->where('status', 'successful')->sum('amount'),
                'spent_this_month'=> Transaction::where('user_id', $userId)->where('status', 'successful')->where('created_at', '>=', $thisMonth)->sum('amount'),
            ],
            'referrals'         => [
                'total'   => $user->referrals()->count(),
                'pending' => $user->referrals()->where('status', 'pending')->count(),
            ],
            'recent_transactions' => Transaction::where('user_id', $userId)
                ->latest()->limit(5)->get()->map(fn($t) => [
                    'reference'    => $t->reference,
                    'service_type' => $t->service_type,
                    'amount'       => $t->amount,
                    'status'       => $t->status,
                    'created_at'   => $t->created_at->diffForHumans(),
                ]),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }
}
