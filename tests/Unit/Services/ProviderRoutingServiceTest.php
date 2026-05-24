<?php

namespace Tests\Unit\Services;

use App\Models\Provider;
use App\Services\Providers\ProviderRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProviderRoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProviderRoutingService::class);
        Cache::flush();
    }

    public function test_returns_highest_priority_active_provider(): void
    {
        Provider::create([
            'name' => 'Provider A', 'slug' => 'provider-a',
            'api_key' => 'key', 'secret_key' => 'secret',
            'endpoint' => 'https://a.test', 'services' => ['airtime'],
            'priority' => 1, 'status' => 'active', 'success_rate' => 100,
        ]);
        Provider::create([
            'name' => 'Provider B', 'slug' => 'provider-b',
            'api_key' => 'key', 'secret_key' => 'secret',
            'endpoint' => 'https://b.test', 'services' => ['airtime'],
            'priority' => 2, 'status' => 'active', 'success_rate' => 95,
        ]);

        $provider = $this->service->getProviderForService('airtime');
        $this->assertEquals('provider-a', $provider->slug);
    }

    public function test_skips_inactive_providers(): void
    {
        Provider::create([
            'name' => 'Inactive', 'slug' => 'inactive-p',
            'api_key' => 'key', 'secret_key' => 'secret',
            'endpoint' => 'https://i.test', 'services' => ['airtime'],
            'priority' => 1, 'status' => 'inactive', 'success_rate' => 100,
        ]);
        Provider::create([
            'name' => 'Active', 'slug' => 'active-p',
            'api_key' => 'key', 'secret_key' => 'secret',
            'endpoint' => 'https://a.test', 'services' => ['airtime'],
            'priority' => 2, 'status' => 'active', 'success_rate' => 90,
        ]);

        $provider = $this->service->getProviderForService('airtime');
        $this->assertEquals('active-p', $provider->slug);
    }

    public function test_throws_when_no_provider_available(): void
    {
        $this->expectException(\App\Exceptions\NoProviderAvailableException::class);
        $this->service->getProviderForService('airtime');
    }

    public function test_provider_enters_backoff_after_max_failures(): void
    {
        $provider = Provider::create([
            'name' => 'Flaky', 'slug' => 'flaky-p',
            'api_key' => 'key', 'secret_key' => 'secret',
            'endpoint' => 'https://f.test', 'services' => ['airtime'],
            'priority' => 1, 'status' => 'active', 'success_rate' => 100,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailure($provider->id, 'airtime', 'timeout');
        }

        $this->assertTrue(Cache::has("provider:backoff:{$provider->id}"));
    }

    public function test_success_clears_failure_count(): void
    {
        $provider = Provider::create([
            'name' => 'Provider', 'slug' => 'prov-clr',
            'api_key' => 'key', 'secret_key' => 'secret',
            'endpoint' => 'https://p.test', 'services' => ['airtime'],
            'priority' => 1, 'status' => 'active', 'success_rate' => 100,
        ]);

        $this->service->recordFailure($provider->id, 'airtime', 'error');
        $this->service->recordFailure($provider->id, 'airtime', 'error');
        $this->service->recordSuccess($provider->id, 'airtime', 150);

        // Failure count should be cleared
        $this->assertFalse(Cache::has("provider:failures:{$provider->id}"));
    }

    public function test_gets_fallback_provider_excluding_failed(): void
    {
        $a = Provider::create(['name' => 'A', 'slug' => 'a-fb', 'api_key' => 'k', 'secret_key' => 's', 'endpoint' => 'https://a.t', 'services' => ['airtime'], 'priority' => 1, 'status' => 'active', 'success_rate' => 100]);
        $b = Provider::create(['name' => 'B', 'slug' => 'b-fb', 'api_key' => 'k', 'secret_key' => 's', 'endpoint' => 'https://b.t', 'services' => ['airtime'], 'priority' => 2, 'status' => 'active', 'success_rate' => 95]);

        $fallback = $this->service->getFallbackProvider('airtime', $a->id);
        $this->assertEquals($b->id, $fallback?->id);
    }
}
