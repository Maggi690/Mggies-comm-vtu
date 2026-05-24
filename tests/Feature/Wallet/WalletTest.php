<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\DTOs\Wallet\CreditWalletDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Wallet $wallet;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $this->user = User::factory()->create([
            'status'          => 'active',
            'transaction_pin' => Hash::make('1234'),
        ]);
        $this->wallet = Wallet::create([
            'user_id'        => $this->user->id,
            'ulid'           => \Str::ulid(),
            'balance'        => 5000,
            'ledger_balance' => 5000,
            'frozen_balance' => 0,
            'currency'       => 'NGN',
            'status'         => 'active',
        ]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    // ─── Balance ─────────────────────────────────────────────────────────────

    public function test_user_can_get_wallet_balance(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wallet/balance')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['balance', 'available_balance', 'ledger_balance', 'frozen_balance', 'currency']])
            ->assertJsonPath('data.balance', 5000.0);
    }

    // ─── Credit ──────────────────────────────────────────────────────────────

    public function test_wallet_can_be_credited(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/credit', [
                'amount'      => 2000,
                'reference'   => 'TEST-CREDIT-001',
                'description' => 'Test credit',
            ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('wallet_transactions', [
            'reference' => 'TEST-CREDIT-001',
            'type'      => 'credit',
            'amount'    => 2000,
        ]);

        $this->wallet->refresh();
        $this->assertEquals(7000, $this->wallet->balance);
    }

    public function test_credit_is_idempotent_with_duplicate_reference(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/credit', ['amount' => 1000, 'reference' => 'DUP-REF', 'description' => 'First']);

        $this->withToken($this->token)
            ->postJson('/api/wallet/credit', ['amount' => 1000, 'reference' => 'DUP-REF', 'description' => 'Second'])
            ->assertStatus(422); // Duplicate reference should fail validation

        $this->assertEquals(1, \App\Models\WalletTransaction::where('reference', 'DUP-REF')->count());
    }

    // ─── Debit ───────────────────────────────────────────────────────────────

    public function test_wallet_can_be_debited_with_correct_pin(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/debit', [
                'amount'      => 1000,
                'reference'   => 'TEST-DEBIT-001',
                'description' => 'Test debit',
                'pin'         => '1234',
            ])->assertOk()->assertJson(['success' => true]);

        $this->wallet->refresh();
        $this->assertEquals(4000, $this->wallet->balance);
    }

    public function test_debit_fails_with_wrong_pin(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/debit', [
                'amount'      => 1000,
                'reference'   => 'TEST-DEBIT-002',
                'description' => 'Test',
                'pin'         => '9999',
            ])->assertStatus(422);

        $this->wallet->refresh();
        $this->assertEquals(5000, $this->wallet->balance); // unchanged
    }

    public function test_debit_fails_with_insufficient_balance(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/debit', [
                'amount'      => 10000, // more than balance
                'reference'   => 'TEST-DEBIT-003',
                'description' => 'Test',
                'pin'         => '1234',
            ])->assertStatus(422);
    }

    // ─── Freeze ──────────────────────────────────────────────────────────────

    public function test_wallet_funds_can_be_frozen(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/freeze', [
                'amount'    => 2000,
                'reason'    => 'Security hold',
                'reference' => 'HOLD-001',
            ])->assertOk()->assertJson(['success' => true]);

        $this->wallet->refresh();
        $this->assertEquals(2000, $this->wallet->frozen_balance);
        $this->assertEquals(3000, $this->wallet->available_balance); // 5000 - 2000
    }

    public function test_cannot_freeze_more_than_available_balance(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/freeze', [
                'amount'    => 9999,
                'reason'    => 'Too much',
                'reference' => 'HOLD-002',
            ])->assertStatus(422);
    }

    // ─── Transactions History ─────────────────────────────────────────────────

    public function test_user_can_get_transaction_history(): void
    {
        // Create some transactions
        $walletService = app(WalletService::class);
        $walletService->credit(new CreditWalletDTO($this->user->id, 500, 'REF-T1', 'Test 1'));
        $walletService->credit(new CreditWalletDTO($this->user->id, 1000, 'REF-T2', 'Test 2'));

        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/transactions')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    // ─── Atomic Transaction Test ──────────────────────────────────────────────

    public function test_concurrent_debits_maintain_balance_integrity(): void
    {
        $walletService = app(WalletService::class);
        $initialBalance = 1000;
        $this->wallet->update(['balance' => $initialBalance, 'ledger_balance' => $initialBalance]);

        // Simulate two debits of 600 — only one should succeed
        $success = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                $walletService->debitInternal($this->user->id, 600, "ATOMIC-{$i}", "Atomic test {$i}");
                $success++;
            } catch (\Exception $e) {
                // Expected for the second one
            }
        }

        $this->wallet->refresh();
        $this->assertEquals(1, $success, 'Only one debit should succeed');
        $this->assertEquals(400, $this->wallet->balance, 'Balance should be 400 after single successful debit');
    }
}
