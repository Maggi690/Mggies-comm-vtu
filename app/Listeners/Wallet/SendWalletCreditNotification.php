<?php

namespace App\Listeners\Wallet;

use App\Events\Wallet\WalletCredited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendWalletCreditNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(WalletCredited $event): void
    {
        $wallet = $event->wallet;
        $txn    = $event->transaction;
        $user   = $wallet->user;

        if (!$user) return;

        // Send email notification
        try {
            \Mail::raw(
                "Dear {$user->first_name},\n\n" .
                "Your wallet has been credited with ₦" . number_format($txn->amount, 2) . ".\n" .
                "New balance: ₦" . number_format($wallet->balance, 2) . "\n" .
                "Reference: {$txn->reference}\n\n" .
                "Thank you for using Universal VTU Pro.",
                fn($msg) => $msg->to($user->email)->subject('Wallet Credited - ₦' . number_format($txn->amount, 2))
            );
        } catch (\Exception $e) {
            Log::error('Failed to send wallet credit notification', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }
    }
}
