<?php

namespace Tests\Feature\Vtu;

use App\Models\DataPlan;
use App\Models\Provider;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VtuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $this->user = User::factory()->create([
            'status'          => 'active',
            'transaction_pin' => Hash::make('1234'),
        ]);
        Wallet::create([
            'user_id'        => $this->user->id,
            'ulid'           => \Str::ulid(),
            'balance'        => 50000,
            'ledger_balance' => 50000,
            'frozen_balance' => 0,
            'currency'       => 'NGN',
            'status'         => 'active',
        ]);
        $this->token    = $this->user->createToken('test')->plainTextToken;
        $this->provider = Provider::where('slug', 'vtpass')->first();
    }

    // ─── Airtime ─────────────────────────────────────────────────────────────

    public function test_airtime_purchase_is_queued(): void
    {
        Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/airtime/purchase', [
                'network' => 'mtn',
                'phone'   => '08012345678',
                'amount'  => 500,
                'pin'     => '1234',
            ])
            ->assertStatus(202)
            ->assertJson(['success' => true, 'data' => ['status' => 'pending']]);

        Queue::assertPushed(\App\Jobs\Vtu\ProcessAirtimeJob::class);
    }

    public function test_airtime_requires_valid_pin(): void
    {
        Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/airtime/purchase', [
                'network' => 'mtn',
                'phone'   => '08012345678',
                'amount'  => 500,
                'pin'     => '9999', // wrong
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Invalid transaction PIN.']);

        Queue::assertNotPushed(\App\Jobs\Vtu\ProcessAirtimeJob::class);
    }

    public function test_airtime_requires_valid_network(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/airtime/purchase', [
                'network' => 'invalid_network',
                'phone'   => '08012345678',
                'amount'  => 500,
                'pin'     => '1234',
            ])
            ->assertStatus(422);
    }

    public function test_airtime_minimum_amount_is_50(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/airtime/purchase', [
                'network' => 'mtn',
                'phone'   => '08012345678',
                'amount'  => 10,
                'pin'     => '1234',
            ])
            ->assertStatus(422);
    }

    // ─── Data ────────────────────────────────────────────────────────────────

    public function test_can_get_data_plans(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/data/plans')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_can_get_data_plans_filtered_by_network(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/data/plans?network=mtn')
            ->assertOk();

        // All returned plans should be MTN
        collect($response->json('data'))->each(function ($plans, $network) {
            $this->assertEquals('mtn', $network);
        });
    }

    public function test_data_purchase_is_queued(): void
    {
        Queue::fake();
        $plan = DataPlan::where('network', 'mtn')->where('status', 'active')->first();

        $this->withToken($this->token)
            ->postJson('/api/data/purchase', [
                'network' => 'mtn',
                'phone'   => '08012345678',
                'plan_id' => $plan->id,
                'pin'     => '1234',
            ])
            ->assertStatus(202)
            ->assertJson(['success' => true]);

        Queue::assertPushed(\App\Jobs\Vtu\ProcessDataJob::class);
    }

    // ─── Cable ───────────────────────────────────────────────────────────────

    public function test_cable_purchase_is_queued(): void
    {
        Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/cable/purchase', [
                'provider'         => 'dstv',
                'smartcard_number' => '1234567890',
                'package_code'     => 'DSTV5',
                'amount'           => 24500,
                'phone'            => '08012345678',
                'pin'              => '1234',
            ])
            ->assertStatus(202);

        Queue::assertPushed(\App\Jobs\Vtu\ProcessCableJob::class);
    }

    public function test_cable_requires_valid_provider(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/cable/purchase', [
                'provider'         => 'invalidtv',
                'smartcard_number' => '123',
                'package_code'     => 'X',
                'amount'           => 1000,
                'pin'              => '1234',
            ])
            ->assertStatus(422);
    }

    // ─── Electricity ─────────────────────────────────────────────────────────

    public function test_electricity_purchase_is_queued(): void
    {
        Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/electricity/purchase', [
                'disco'        => 'ekedc',
                'meter_number' => '1234567890',
                'meter_type'   => 'prepaid',
                'amount'       => 5000,
                'phone'        => '08012345678',
                'pin'          => '1234',
            ])
            ->assertStatus(202);

        Queue::assertPushed(\App\Jobs\Vtu\ProcessElectricityJob::class);
    }

    public function test_electricity_minimum_amount_is_100(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/electricity/purchase', [
                'disco'        => 'ekedc',
                'meter_number' => '1234567890',
                'meter_type'   => 'prepaid',
                'amount'       => 50,
                'pin'          => '1234',
            ])
            ->assertStatus(422);
    }

    // ─── Exam ────────────────────────────────────────────────────────────────

    public function test_exam_purchase_is_queued(): void
    {
        Queue::fake();

        $this->withToken($this->token)
            ->postJson('/api/exam/purchase', [
                'exam_type' => 'waec',
                'quantity'  => 1,
                'pin'       => '1234',
            ])
            ->assertStatus(202);

        Queue::assertPushed(\App\Jobs\Vtu\ProcessExamJob::class);
    }
}
