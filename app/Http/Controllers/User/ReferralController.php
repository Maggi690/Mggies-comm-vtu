<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $referrals = Referral::where('referrer_id', $user->id)
            ->with('referee:id,first_name,last_name,email,created_at')
            ->latest()
            ->paginate(20);

        $totalEarnings = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        $pendingEarnings = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'referral_code'   => $user->referral_code,
                'referral_link'   => config('app.url') . '/register?ref=' . $user->referral_code,
                'total_referrals' => $referrals->total(),
                'total_earnings'  => $totalEarnings,
                'pending_earnings'=> $pendingEarnings,
                'referrals'       => $referrals,
            ],
        ]);
    }
}
