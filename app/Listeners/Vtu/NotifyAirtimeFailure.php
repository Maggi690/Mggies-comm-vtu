<?php
namespace App\Listeners\Vtu;
use App\Events\Vtu\AirtimeFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
class NotifyAirtimeFailure implements ShouldQueue {
    public string $queue = 'notifications';
    public function handle(AirtimeFailed $event): void {
        $txn  = $event->transaction;
        $user = $txn->user;
        if (!$user) return;
        try {
            \Mail::raw(
                "Dear {$user->first_name},\n\nYour airtime purchase (Ref: {$txn->reference}) failed and ₦{$txn->amount} has been refunded to your wallet.",
                fn($m) => $m->to($user->email)->subject('Airtime Purchase Failed - Refunded')
            );
        } catch (\Exception $e) {}
    }
}
