<?php
namespace App\Listeners\Vtu;
use App\Events\Vtu\AirtimePurchased;
use Illuminate\Contracts\Queue\ShouldQueue;
class SendAirtimePurchaseNotification implements ShouldQueue {
    public string $queue = 'notifications';
    public function handle(AirtimePurchased $event): void {
        $txn  = $event->transaction;
        $user = $txn->user;
        if (!$user) return;
        try {
            \Mail::raw(
                "Dear {$user->first_name},\n\nAirtime of ₦{$txn->amount} was sent to {$txn->phone}.\nRef: {$txn->reference}",
                fn($m) => $m->to($user->email)->subject('Airtime Purchase Successful')
            );
        } catch (\Exception $e) {}
    }
}
