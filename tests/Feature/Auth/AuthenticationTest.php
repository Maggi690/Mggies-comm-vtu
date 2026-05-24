<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
    }

    // ─── Registration ────────────────────────────────────────────────────────

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'phone'                 => '08012345678',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['user' => ['id', 'email', 'first_name'], 'token'],
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('wallets', []);

        // Wallet should be created
        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user->wallet);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/auth/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'email'                 => 'dup@example.com',
            'phone'                 => '08087654321',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertStatus(422)->assertJsonPath('errors.email', fn($v) => !empty($v));
    }

    public function test_registration_fails_with_weak_password(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john2@example.com',
            'phone'                 => '08012345679',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422);
    }

    public function test_registration_with_valid_referral_code(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'TESTREF1']);

        $this->postJson('/api/auth/register', [
            'first_name'            => 'Referred',
            'last_name'             => 'User',
            'email'                 => 'referred@example.com',
            'phone'                 => '08011112222',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referral_code'         => 'TESTREF1',
        ])->assertStatus(201);

        $this->assertDatabaseHas('referrals', ['referrer_id' => $referrer->id]);
    }

    // ─── Login ───────────────────────────────────────────────────────────────

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'login@example.com',
            'password' => Hash::make('SecurePass123!'),
            'status'   => 'active',
        ]);
        Wallet::create(['user_id' => $user->id, 'ulid' => \Str::ulid(), 'balance' => 0, 'ledger_balance' => 0, 'frozen_balance' => 0, 'currency' => 'NGN']);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['user', 'token']])
            ->assertJson(['success' => true]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('correct')]);
        $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertStatus(401);
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email'    => 'suspended@example.com',
            'password' => Hash::make('SecurePass123!'),
            'status'   => 'suspended',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com', 'password' => 'SecurePass123!',
        ])->assertStatus(401);
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_logout(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    // ─── Transaction PIN ─────────────────────────────────────────────────────

    public function test_user_can_set_transaction_pin(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/user/set-pin', [
                'pin'              => '1234',
                'pin_confirmation' => '1234',
            ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull($user->fresh()->transaction_pin);
    }

    public function test_pin_must_be_4_digits(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/user/set-pin', ['pin' => '123', 'pin_confirmation' => '123'])
            ->assertStatus(422);
    }

    // ─── Rate Limiting ────────────────────────────────────────────────────────

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'rate@test.com', 'password' => 'wrong']);
        }
        // 11th attempt should be throttled
        $this->postJson('/api/auth/login', ['email' => 'rate@test.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
