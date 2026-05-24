<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind integrations as singletons
        $this->app->singleton(\App\Integrations\Monnify\MonnifyIntegration::class);
        $this->app->singleton(\App\Integrations\Paystack\PaystackIntegration::class);
        $this->app->singleton(\App\Integrations\Flutterwave\FlutterwaveIntegration::class);
        $this->app->singleton(\App\Integrations\Providers\VtpassIntegration::class);
        $this->app->singleton(\App\Integrations\Providers\HusmodataIntegration::class);
        $this->app->singleton(\App\Integrations\Providers\GsubzIntegration::class);

        // Bind services
        $this->app->singleton(\App\Services\Wallet\WalletService::class);
        $this->app->singleton(\App\Services\Providers\ProviderRoutingService::class);
        $this->app->singleton(\App\Services\AuthService::class);
    }

    public function boot(): void
    {
        // Enforce JSON responses for API
        \Illuminate\Http\Request::macro('expectsJson', function () {
            return true;
        });
    }
}
