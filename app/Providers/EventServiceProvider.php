<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Wallet events
        \App\Events\Wallet\WalletCredited::class => [
            \App\Listeners\Wallet\SendWalletCreditNotification::class,
        ],
        \App\Events\Wallet\WalletDebited::class => [
            \App\Listeners\Wallet\SendWalletDebitNotification::class,
        ],

        // VTU events
        \App\Events\Vtu\AirtimePurchased::class => [
            \App\Listeners\Vtu\SendAirtimePurchaseNotification::class,
            \App\Listeners\Vtu\ProcessReferralCommission::class,
        ],
        \App\Events\Vtu\AirtimeFailed::class => [
            \App\Listeners\Vtu\NotifyAirtimeFailure::class,
        ],

        // User events
        \Illuminate\Auth\Events\Registered::class => [
            \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
