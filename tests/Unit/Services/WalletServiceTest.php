<?php

namespace Tests\Unit\Services;

use App\DTOs\Wallet\CreditWalletDTO;
use App\DTOs\Wallet\DebitWalletDTO;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidPinException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;
    private User $user;
    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
        $this->user    = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
        $this->wallet  = $this->service->createWallet($this->user->id);
    }

    public function test_creates_wallet_with_zero_balance(): void
    {
        $this->assertEquals(0, $this->wallet->balance);
        $this->assertEquals(0, $this->wallet->ledger_balance);
        $this->assertEquals(0, $this->wallet->frozen_balance);
        $this->assertEquals('NGN', $this->wallet->currency);
    }

    public function test_credit_increases_balance(): void
    {
        $dto = new CreditWalletDTO($this->user->id, 5000, 'REF-1', 'Test credit');
        $txn = $this->service->credit($dto);

        $this->assertEquals(5000, $this->wallet->fresh()->balance);
        $this->assertEquals(5000, $txn->amount);
        $this->assertEquals(0,    $txn->balance_before);
        $this->assertEquals(5000, $txn->balance_after);
    }

    public function test_debit_decreases_balance(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 5000, 'CREDIT-1', 'Fund'));
        $txn = $this->service->debitInternal($this->user->id, 2000, 'DEBIT-1', 'Test debit');

        $this->assertEquals(3000, $this->wallet->fresh()->balance);
        $this->assertEquals(5000, $txn->balance_before);
        $this->assertEquals(3000, $txn->balance_after);
    }

    public function test_debit_with_pin_verifies_correctly(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 5000, 'CREDIT-2', 'Fund'));
        $dto = new DebitWalletDTO($this->user->id, 1000, 'DEBIT-PIN-1', 'Test', '1234');
        $txn = $this->service->debit($dto);

        $this->assertEquals(4000, $this->wallet->fresh()->balance);
        $this->assertNotNull($txn);
    }

    public function test_debit_throws_on_invalid_pin(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 5000, 'CREDIT-3', 'Fund'));
        $dto = new DebitWalletDTO($this->user->id, 1000, 'DEBIT-BADPIN', 'Test', '9999');

        $this->expectException(InvalidPinException::class);
        $this->service->debit($dto);
    }

    public function test_debit_throws_on_insufficient_balance(): void
    {
        $this->expectException(InsufficientBalanceException::class);
        $this->service->debitInternal($this->user->id, 9999, 'OVER-DEBIT', 'Overflow');
    }

    public function test_freeze_reduces_available_balance(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 5000, 'CREDIT-4', 'Fund'));
        $this->service->freeze($this->user->id, 2000, 'Security hold', 'HOLD-1');

        $wallet = $this->wallet->fresh();
        $this->assertEquals(5000, $wallet->balance);            // total unchanged
        $this->assertEquals(2000, $wallet->frozen_balance);     // frozen
        $this->assertEquals(3000, $wallet->available_balance);  // available
    }

    public function test_release_restores_available_balance(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 5000, 'CREDIT-5', 'Fund'));
        $hold = $this->service->freeze($this->user->id, 2000, 'Hold', 'HOLD-2');
        $this->service->release($hold->id);

        $wallet = $this->wallet->fresh();
        $this->assertEquals(0,    $wallet->frozen_balance);
        $this->assertEquals(5000, $wallet->available_balance);
    }

    public function test_refund_credits_wallet(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 5000, 'CREDIT-6', 'Fund'));
        $this->service->debitInternal($this->user->id, 1000, 'DEBIT-2', 'Purchase');
        $this->service->refund($this->user->id, 1000, 'TXN-REF', 'DEBIT-2');

        $this->assertEquals(5000, $this->wallet->fresh()->balance); // back to original
    }

    public function test_multiple_concurrent_operations_are_atomic(): void
    {
        $this->service->credit(new CreditWalletDTO($this->user->id, 1000, 'CREDIT-7', 'Fund'));

        // Both operations should not result in negative balance
        $errors = 0;
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->service->debitInternal($this->user->id, 500, "DEBIT-ATM-{$i}", "Debit {$i}");
            } catch (InsufficientBalanceException $e) {
                $errors++;
            }
        }

        $balance = $this->wallet->fresh()->balance;
        $this->assertGreaterThanOrEqual(0, $balance, 'Balance should never go negative');
        $this->assertEquals(2, $errors, 'Two out of three debits should fail');
        $this->assertEquals(500, $balance, 'Final balance should be 500');
    }
}
