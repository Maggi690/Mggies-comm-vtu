<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\WalletController;
use App\Http\Controllers\User\AirtimeController;
use App\Http\Controllers\User\DataController;
use App\Http\Controllers\User\CableController;
use App\Http\Controllers\User\ElectricityController;
use App\Http\Controllers\User\ExamController;
use App\Http\Controllers\User\PaymentController;
use App\Http\Controllers\User\SupportController;
use App\Http\Controllers\User\ApiKeyController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminProviderController;
use App\Http\Controllers\Admin\AdminTransactionController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminBlacklistController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Webhooks\WebhookController;
use App\Http\Controllers\Api\V1\ApiV1Controller;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════
// HEALTH CHECK
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/health', fn() => response()->json([
    'status'  => 'ok',
    'service' => 'Universal VTU Pro API',
    'version' => '1.0.0',
    'time'    => now()->toIso8601String(),
]));

// ═══════════════════════════════════════════════════════════════════════════
// WEBHOOKS (no auth — validated by signature)
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/monnify',          [WebhookController::class, 'monnify'])->name('monnify');
    Route::post('/paystack',         [WebhookController::class, 'paystack'])->name('paystack');
    Route::post('/flutterwave',      [WebhookController::class, 'flutterwave'])->name('flutterwave');
    Route::post('/developer/{key}',  [WebhookController::class, 'developer'])->name('developer');
});

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATION
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('auth')->name('auth.')->middleware(['throttle:10,1'])->group(function () {
    Route::post('/register',          [AuthController::class, 'register'])->name('register');
    Route::post('/login',             [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password',   [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password',    [AuthController::class, 'resetPassword'])->name('reset-password');
    Route::post('/verify-email',      [AuthController::class, 'verifyEmail'])->name('verify-email');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATED USER ROUTES
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth:sanctum', 'blacklist', 'throttle:120,1'])->group(function () {

    // ── Profile & PIN ──────────────────────────────────────────────────────
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/profile',          fn(\Illuminate\Http\Request $r) => response()->json(['success' => true, 'data' => new \App\Http\Resources\User\UserResource($r->user()->load('wallet'))]));
        Route::put('/profile',          [\App\Http\Controllers\User\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/set-pin',         [AuthController::class, 'setPin'])->name('set-pin');
        Route::post('/reset-pin',       [AuthController::class, 'resetPin'])->name('reset-pin');
        Route::get('/transactions',     [\App\Http\Controllers\User\TransactionController::class, 'index'])->name('transactions');
        Route::get('/transactions/{id}',[App\Http\Controllers\User\TransactionController::class, 'show'])->name('transactions.show');
        Route::get('/referrals',        [\App\Http\Controllers\User\ReferralController::class, 'index'])->name('referrals');
        Route::get('/dashboard',        [\App\Http\Controllers\User\DashboardController::class, 'index'])->name('dashboard');
    });

    // ── Wallet ──────────────────────────────────────────────────────────────
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/balance',              [WalletController::class, 'balance'])->name('balance');
        Route::get('/transactions',         [WalletController::class, 'transactions'])->name('transactions');
        Route::post('/credit',              [WalletController::class, 'credit'])->name('credit')->middleware('throttle:20,1');
        Route::post('/debit',               [WalletController::class, 'debit'])->name('debit')->middleware('throttle:20,1');
        Route::post('/freeze',              [WalletController::class, 'freeze'])->name('freeze');
        Route::post('/release/{holdId}',    [WalletController::class, 'release'])->name('release');
    });

    // ── Payments ────────────────────────────────────────────────────────────
    Route::prefix('payments')->name('payments.')->middleware('throttle:30,1')->group(function () {
        Route::post('/initialize',           [PaymentController::class, 'initialize'])->name('initialize');
        Route::post('/verify',               [PaymentController::class, 'verify'])->name('verify');
        Route::post('/reserved-account',     [PaymentController::class, 'createReservedAccount'])->name('reserved-account');
        Route::post('/virtual-account',      [PaymentController::class, 'createVirtualAccount'])->name('virtual-account');
    });

    // ── Airtime ─────────────────────────────────────────────────────────────
    Route::prefix('airtime')->name('airtime.')->middleware('throttle:60,1')->group(function () {
        Route::post('/purchase',        [AirtimeController::class, 'purchase'])->name('purchase');
        Route::get('/status/{id}',      [AirtimeController::class, 'status'])->name('status');
    });

    // ── Data ────────────────────────────────────────────────────────────────
    Route::prefix('data')->name('data.')->group(function () {
        Route::get('/plans',            [DataController::class, 'plans'])->name('plans');
        Route::post('/purchase',        [DataController::class, 'purchase'])->name('purchase')->middleware('throttle:60,1');
    });

    // ── Cable TV ────────────────────────────────────────────────────────────
    Route::prefix('cable')->name('cable.')->group(function () {
        Route::post('/validate',        [CableController::class, 'validate'])->name('validate');
        Route::post('/purchase',        [CableController::class, 'purchase'])->name('purchase')->middleware('throttle:30,1');
    });

    // ── Electricity ─────────────────────────────────────────────────────────
    Route::prefix('electricity')->name('electricity.')->group(function () {
        Route::post('/validate',        [ElectricityController::class, 'validate'])->name('validate');
        Route::post('/purchase',        [ElectricityController::class, 'purchase'])->name('purchase')->middleware('throttle:30,1');
    });

    // ── Exam Pins ────────────────────────────────────────────────────────────
    Route::prefix('exam')->name('exam.')->group(function () {
        Route::post('/purchase',        [ExamController::class, 'purchase'])->name('purchase')->middleware('throttle:20,1');
    });

    // ── Support ─────────────────────────────────────────────────────────────
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/tickets',              [SupportController::class, 'myTickets'])->name('tickets');
        Route::post('/ticket',              [SupportController::class, 'createTicket'])->name('create');
        Route::get('/ticket/{id}',          [SupportController::class, 'showTicket'])->name('show');
        Route::post('/ticket/{id}/reply',   [SupportController::class, 'reply'])->name('reply');
    });

    // ── API Keys (Developer) ────────────────────────────────────────────────
    Route::prefix('api-keys')->name('api-keys.')->group(function () {
        Route::get('/',          [ApiKeyController::class, 'index'])->name('index');
        Route::post('/',         [ApiKeyController::class, 'store'])->name('store');
        Route::put('/{id}',      [ApiKeyController::class, 'update'])->name('update');
        Route::delete('/{id}',   [ApiKeyController::class, 'revoke'])->name('revoke');
        Route::get('/{id}/usage',[ApiKeyController::class, 'usage'])->name('usage');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// ADMIN ROUTES
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'blacklist', 'role:admin|assistant_admin|customer_support', 'throttle:200,1'])->group(function () {

    // ── Users ────────────────────────────────────────────────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/',                     [AdminUserController::class, 'index'])->name('index');
        Route::get('/{id}',                 [AdminUserController::class, 'show'])->name('show');
        Route::put('/{id}',                 [AdminUserController::class, 'update'])->name('update');
        Route::delete('/{id}',              [AdminUserController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/suspend',        [AdminUserController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/activate',       [AdminUserController::class, 'activate'])->name('activate');
        Route::post('/{id}/credit-wallet',  [AdminUserController::class, 'creditWallet'])->name('credit-wallet');
        Route::post('/{id}/debit-wallet',   [AdminUserController::class, 'debitWallet'])->name('debit-wallet');
        Route::get('/{id}/transactions',    [AdminUserController::class, 'transactions'])->name('transactions');
    });

    // ── Providers ────────────────────────────────────────────────────────────
    Route::prefix('providers')->name('providers.')->group(function () {
        Route::get('/',                     [AdminProviderController::class, 'index'])->name('index');
        Route::post('/',                    [AdminProviderController::class, 'store'])->name('store');
        Route::get('/{id}',                 [AdminProviderController::class, 'show'])->name('show');
        Route::put('/{id}',                 [AdminProviderController::class, 'update'])->name('update');
        Route::delete('/{id}',              [AdminProviderController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/activate',       [AdminProviderController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate',     [AdminProviderController::class, 'deactivate'])->name('deactivate');
        Route::post('/reorder',             [AdminProviderController::class, 'reorder'])->name('reorder');
        Route::get('/{id}/services',        [AdminProviderController::class, 'services'])->name('services');
        Route::post('/{id}/services',       [AdminProviderController::class, 'addService'])->name('services.add');
        Route::get('/{id}/stats',           [AdminProviderController::class, 'stats'])->name('stats');
    });

    // ── Transactions ─────────────────────────────────────────────────────────
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/',             [AdminTransactionController::class, 'index'])->name('index');
        Route::get('/{id}',         [AdminTransactionController::class, 'show'])->name('show');
        Route::post('/{id}/refund', [AdminTransactionController::class, 'refund'])->name('refund');
        Route::post('/{id}/retry',  [AdminTransactionController::class, 'retry'])->name('retry');
        Route::post('/export',      [AdminTransactionController::class, 'export'])->name('export');
    });

    // ── Reports ──────────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/dashboard',            [AdminReportController::class, 'dashboard'])->name('dashboard');
        Route::get('/revenue',              [AdminReportController::class, 'revenue'])->name('revenue');
        Route::get('/by-service',           [AdminReportController::class, 'byService'])->name('by-service');
        Route::get('/provider-performance', [AdminReportController::class, 'providerPerformance'])->name('provider-performance');
        Route::get('/user-growth',          [AdminReportController::class, 'userGrowth'])->name('user-growth');
        Route::get('/funding',              [AdminReportController::class, 'funding'])->name('funding');
        Route::post('/export',              [AdminReportController::class, 'export'])->name('export');
    });

    // ── Blacklist ────────────────────────────────────────────────────────────
    Route::prefix('blacklist')->name('blacklist.')->group(function () {
        Route::get('/',                         [AdminBlacklistController::class, 'index'])->name('index');
        Route::post('/ip',                      [AdminBlacklistController::class, 'blacklistIp'])->name('ip');
        Route::post('/email',                   [AdminBlacklistController::class, 'blacklistEmail'])->name('email');
        Route::post('/number',                  [AdminBlacklistController::class, 'blacklistNumber'])->name('number');
        Route::post('/bvn',                     [AdminBlacklistController::class, 'blacklistBvn'])->name('bvn');
        Route::delete('/{type}/{id}',           [AdminBlacklistController::class, 'remove'])->name('remove');
    });

    // ── Settings ─────────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/',             [AdminSettingsController::class, 'index'])->name('index');
        Route::put('/',             [AdminSettingsController::class, 'update'])->name('update');
        Route::get('/data-plans',   [AdminSettingsController::class, 'dataPlanRates'])->name('data-plans.get');
        Route::put('/data-plans',   [AdminSettingsController::class, 'dataPlanRates'])->name('data-plans.update');
    });

    // ── Support (Admin) ──────────────────────────────────────────────────────
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/',                     [\App\Http\Controllers\Admin\AdminSupportController::class, 'index'])->name('index');
        Route::get('/{id}',                 [\App\Http\Controllers\Admin\AdminSupportController::class, 'show'])->name('show');
        Route::post('/{id}/reply',          [\App\Http\Controllers\Admin\AdminSupportController::class, 'reply'])->name('reply');
        Route::post('/{id}/close',          [\App\Http\Controllers\Admin\AdminSupportController::class, 'close'])->name('close');
        Route::post('/{id}/assign',         [\App\Http\Controllers\Admin\AdminSupportController::class, 'assign'])->name('assign');
    });

    // ── Wallets (Admin) ──────────────────────────────────────────────────────
    Route::prefix('wallets')->name('wallets.')->group(function () {
        Route::get('/',         [\App\Http\Controllers\Admin\AdminWalletController::class, 'index'])->name('index');
        Route::get('/{id}',     [\App\Http\Controllers\Admin\AdminWalletController::class, 'show'])->name('show');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// DEVELOPER API v1
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('v1')->name('v1.')->middleware(['api.key', 'throttle:300,1'])->group(function () {
    Route::post('/airtime',         [ApiV1Controller::class, 'airtime'])->name('airtime');
    Route::post('/data',            [ApiV1Controller::class, 'data'])->name('data');
    Route::post('/cable',           [ApiV1Controller::class, 'cable'])->name('cable');
    Route::post('/electricity',     [ApiV1Controller::class, 'electricity'])->name('electricity');
    Route::post('/exam',            [ApiV1Controller::class, 'exam'])->name('exam');
    Route::get('/transaction/{ref}',[ApiV1Controller::class, 'transaction'])->name('transaction');
    Route::get('/data-plans',       [ApiV1Controller::class, 'dataPlans'])->name('data-plans');
    Route::get('/balance',          [ApiV1Controller::class, 'balance'])->name('balance');
});
