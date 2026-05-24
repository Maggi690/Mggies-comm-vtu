<?php
namespace App\Listeners\Wallet;
use App\Events\Wallet\WalletDebited;
use Illuminate\Contracts\Queue\ShouldQueue;
class SendWalletDebitNotification implements ShouldQueue {
    public string $queue = 'notifications';
    public function handle(WalletDebited $event): void {
        $user = $event->wallet->user;
        if (!$user) return;
        try {
            \Mail::raw(
                "Dear {$user->first_name},\n\nYour wallet was debited ₦" . number_format($event->transaction->amount, 2) .
                ".\nBalance: ₦" . number_format($event->wallet->balance, 2) . "\nRef: {$event->transaction->reference}",
                fn($m) => $m->to($user->email)->subject('Wallet Debited')
            );
        } catch (\Exception $e) {}
    }
}
