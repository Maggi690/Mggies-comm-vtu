<?php
namespace App\Listeners\Vtu;
use App\Events\Vtu\AirtimePurchased;
use App\Models\Referral;
use App\Models\ReferralCommission;
use Illuminate\Contracts\Queue\ShouldQueue;
class ProcessReferralCommission implements ShouldQueue {
    public string $queue = 'default';
    public function handle(AirtimePurchased $event): void {
        $txn      = $event->transaction;
        $referral = Referral::where('referee_id', $txn->user_id)->where('status', 'pending')->first();
        if (!$referral) return;
        $rate = (float) \DB::table('settings')->where('key', 'referral_commission_rate')->value('value') ?? 0.5;
        $commission = ($txn->amount * $rate) / 100;
        if ($commission <= 0) return;
        ReferralCommission::create([
            'referrer_id'     => $referral->referrer_id,
            'referee_id'      => $txn->user_id,
            'transaction_id'  => $txn->id,
            'amount'          => $commission,
            'commission_rate' => $rate,
            'type'            => 'transaction',
            'status'          => 'pending',
        ]);
    }
}
