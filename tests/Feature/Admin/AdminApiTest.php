<?php

namespace Tests\Feature\Admin;

use App\Models\Provider;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private string $adminToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $this->admin = User::where('user_type', 'admin')->first();
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

        $this->regularUser = User::factory()->create(['status' => 'active', 'user_type' => 'user']);
        Wallet::create([
            'user_id' => $this->regularUser->id, 'ulid' => \Str::ulid(),
            'balance' => 1000, 'ledger_balance' => 1000, 'frozen_balance' => 0, 'currency' => 'NGN',
        ]);
        $this->regularUser->assignRole('user');
        $this->userToken = $this->regularUser->createToken('user')->plainTextToken;
    }

    // ─── Access Control ──────────────────────────────────────────────────────

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $this->withToken($this->userToken)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ─── User Management ─────────────────────────────────────────────────────

    public function test_admin_can_list_users_with_filters(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/users?status=active&per_page=5')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_admin_can_search_users(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/users?search=' . $this->regularUser->email)
            ->assertOk()
            ->assertJsonPath('data.0.email', $this->regularUser->email);
    }

    public function test_admin_can_suspend_user(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$this->regularUser->id}/suspend")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals('suspended', $this->regularUser->fresh()->status);
    }

    public function test_admin_can_activate_user(): void
    {
        $this->regularUser->update(['status' => 'suspended']);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$this->regularUser->id}/activate")
            ->assertOk();

        $this->assertEquals('active', $this->regularUser->fresh()->status);
    }

    public function test_admin_can_credit_user_wallet(): void
    {
        $balanceBefore = $this->regularUser->wallet->balance;

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$this->regularUser->id}/credit-wallet", [
                'amount'      => 5000,
                'description' => 'Admin test credit',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals($balanceBefore + 5000, $this->regularUser->wallet->fresh()->balance);
    }

    // ─── Provider Management ──────────────────────────────────────────────────

    public function test_admin_can_list_providers(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/providers')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_admin_can_add_new_provider(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/providers', [
                'name'       => 'Test Provider',
                'slug'       => 'test-provider-new',
                'api_key'    => 'test-api-key',
                'secret_key' => 'test-secret-key',
                'endpoint'   => 'https://test.provider.com/api',
                'services'   => ['airtime', 'data'],
                'priority'   => 5,
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('providers', ['slug' => 'test-provider-new']);
    }

    public function test_admin_can_toggle_provider_status(): void
    {
        $provider = Provider::where('status', 'active')->first();

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/providers/{$provider->id}/deactivate")
            ->assertOk();

        $this->assertEquals('inactive', $provider->fresh()->status);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/providers/{$provider->id}/activate")
            ->assertOk();

        $this->assertEquals('active', $provider->fresh()->status);
    }

    // ─── Transaction Management ───────────────────────────────────────────────

    public function test_admin_can_list_transactions(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/transactions')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    public function test_admin_can_refund_failed_transaction(): void
    {
        $txn = Transaction::create([
            'user_id'      => $this->regularUser->id,
            'ulid'         => \Str::ulid(),
            'reference'    => 'FAIL-TXN-TEST',
            'type'         => 'debit',
            'service_type' => 'airtime',
            'amount'       => 500,
            'status'       => 'failed',
        ]);

        $walletBefore = $this->regularUser->wallet->fresh()->balance;

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/transactions/{$txn->id}/refund", ['reason' => 'Manual refund test'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals($walletBefore + 500, $this->regularUser->wallet->fresh()->balance);
        $this->assertEquals('refunded', $txn->fresh()->status);
    }

    // ─── Reports ─────────────────────────────────────────────────────────────

    public function test_admin_can_view_dashboard(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/reports/dashboard')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['users', 'transactions', 'service_breakdown']]);
    }

    public function test_admin_can_get_revenue_report(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/reports/revenue?from=2024-01-01&to=2024-12-31')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ─── Blacklist ───────────────────────────────────────────────────────────

    public function test_admin_can_blacklist_ip(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/blacklist/ip', [
                'ip_address' => '192.168.1.100',
                'reason'     => 'Suspicious activity',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('blacklisted_ips', ['ip_address' => '192.168.1.100']);
    }

    public function test_admin_can_remove_from_blacklist(): void
    {
        $record = \App\Models\BlacklistedIp::create(['ip_address' => '10.0.0.1', 'reason' => 'Test']);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/blacklist/ip/{$record->id}")
            ->assertOk();

        $this->assertDatabaseMissing('blacklisted_ips', ['ip_address' => '10.0.0.1']);
    }
}
