<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ── Daily report generation ──────────────────────────────────────────
        $schedule->command('reports:daily')->dailyAt('01:00');
        $schedule->command('reports:monthly')->monthlyOn(1, '02:00');

        // ── Reset API key daily usage ─────────────────────────────────────────
        $schedule->call(function () {
            \App\Models\ApiKey::where('daily_usage', '>', 0)->update(['daily_usage' => 0]);
        })->dailyAt('00:00')->name('reset-api-daily-usage');

        // ── Reset API key monthly usage ───────────────────────────────────────
        $schedule->call(function () {
            \App\Models\ApiKey::where('monthly_usage', '>', 0)->update(['monthly_usage' => 0]);
        })->monthlyOn(1, '00:00')->name('reset-api-monthly-usage');

        // ── Clean up old webhook logs (keep 30 days) ──────────────────────────
        $schedule->call(function () {
            \App\Models\WebhookLog::where('created_at', '<', now()->subDays(30))->delete();
        })->weekly()->name('cleanup-webhook-logs');

        // ── Clean up expired provider backoffs ────────────────────────────────
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::flush();
        })->everyFiveMinutes()->name('flush-provider-backoff-cache');

        // ── Sync pending referral commissions ─────────────────────────────────
        $schedule->call(function () {
            \App\Models\ReferralCommission::where('status', 'pending')
                ->where('created_at', '<', now()->subDays(1))
                ->each(function ($commission) {
                    try {
                        $walletService = app(\App\Services\Wallet\WalletService::class);
                        $walletService->credit(new \App\DTOs\Wallet\CreditWalletDTO(
                            userId:      $commission->referrer_id,
                            amount:      $commission->amount,
                            reference:   'REF-COMM-' . $commission->id,
                            description: 'Referral commission',
                            type:        'commission',
                            meta:        ['commission_id' => $commission->id],
                        ));
                        $commission->update(['status' => 'paid', 'paid_at' => now()]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Commission payout failed', ['id' => $commission->id, 'error' => $e->getMessage()]);
                    }
                });
        })->hourly()->name('process-referral-commissions');

        // ── Retry stuck pending transactions (older than 30 min) ─────────────
        $schedule->call(function () {
            \App\Models\Transaction::where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->where('retries', '<', 3)
                ->each(fn($txn) => \App\Jobs\Vtu\RetryTransactionJob::dispatch($txn));
        })->everyThirtyMinutes()->name('retry-stuck-transactions');

        // ── Prune activity logs (keep 90 days) ───────────────────────────────
        $schedule->command('activitylog:clean --days=90')->weekly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
