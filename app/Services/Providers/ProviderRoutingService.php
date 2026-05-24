<?php

namespace App\Services\Providers;

use App\Models\Provider;
use App\Models\ProviderLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProviderRoutingService
{
    private const CACHE_TTL       = 300; // 5 minutes
    private const FAILURE_BACKOFF = 600; // 10 min backoff after repeated failures
    private const MAX_FAILURES    = 5;

    /**
     * Get the best available provider for a service type.
     * Falls through priority list automatically on failure.
     */
    public function getProviderForService(string $serviceType, ?string $network = null): Provider
    {
        $providers = $this->getAvailableProviders($serviceType, $network);

        if ($providers->isEmpty()) {
            throw new \App\Exceptions\NoProviderAvailableException(
                "No active provider available for service: {$serviceType}"
            );
        }

        return $providers->first();
    }

    /**
     * Get all available providers sorted by priority and health score.
     */
    public function getAvailableProviders(string $serviceType, ?string $network = null)
    {
        $cacheKey = "providers:{$serviceType}:" . ($network ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serviceType, $network) {
            $query = Provider::where('status', 'active')
                ->whereJsonContains('services', $serviceType)
                ->orderBy('priority', 'asc');

            return $query->get()->filter(function (Provider $provider) use ($serviceType, $network) {
                // Filter out providers in backoff
                if ($this->isInBackoff($provider->id)) {
                    return false;
                }

                // Check network support if specified
                if ($network && !$this->providerSupportsNetwork($provider, $serviceType, $network)) {
                    return false;
                }

                return true;
            })->sortBy(function (Provider $provider) {
                return $this->calculateHealthScore($provider);
            });
        });
    }

    /**
     * Get the next fallback provider after one fails.
     */
    public function getFallbackProvider(string $serviceType, int $failedProviderId, ?string $network = null): ?Provider
    {
        $providers = $this->getAvailableProviders($serviceType, $network)
            ->filter(fn(Provider $p) => $p->id !== $failedProviderId);

        return $providers->first();
    }

    /**
     * Record a successful API call.
     */
    public function recordSuccess(int $providerId, string $serviceType, int $responseTimeMs): void
    {
        $this->updateProviderStats($providerId, true, $responseTimeMs);
        $this->clearFailureCount($providerId);
        Cache::forget("providers:{$serviceType}:*");
    }

    /**
     * Record a failed API call and potentially trigger backoff.
     */
    public function recordFailure(int $providerId, string $serviceType, string $error): void
    {
        $this->updateProviderStats($providerId, false, 0);
        $failures = $this->incrementFailureCount($providerId);

        Log::warning('Provider API failure', [
            'provider_id'  => $providerId,
            'service'      => $serviceType,
            'error'        => $error,
            'total_failures' => $failures,
        ]);

        if ($failures >= self::MAX_FAILURES) {
            $this->setBackoff($providerId);
            Log::error("Provider #{$providerId} entered backoff after {$failures} consecutive failures");
        }

        Cache::forget("providers:{$serviceType}:*");
    }

    /**
     * Log provider API interaction.
     */
    public function logInteraction(int $providerId, ?int $transactionId, string $action, array $request, array $response, string $status, int $responseTimeMs, ?string $error = null): void
    {
        ProviderLog::create([
            'provider_id'       => $providerId,
            'transaction_id'    => $transactionId,
            'action'            => $action,
            'request'           => $request,
            'response'          => $response,
            'status'            => $status,
            'response_time_ms'  => $responseTimeMs,
            'error'             => $error,
        ]);
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    private function calculateHealthScore(Provider $provider): float
    {
        $successRate  = (float) $provider->success_rate;
        $responseTime = (float) $provider->avg_response_time;

        // Higher is worse — we want lowest score first
        return (100 - $successRate) + ($responseTime / 1000);
    }

    private function isInBackoff(int $providerId): bool
    {
        return Cache::has("provider:backoff:{$providerId}");
    }

    private function setBackoff(int $providerId): void
    {
        Cache::put("provider:backoff:{$providerId}", true, self::FAILURE_BACKOFF);
    }

    private function incrementFailureCount(int $providerId): int
    {
        $key = "provider:failures:{$providerId}";
        if (!Cache::has($key)) {
            Cache::put($key, 0, 3600);
        }
        return Cache::increment($key);
    }

    private function clearFailureCount(int $providerId): void
    {
        Cache::forget("provider:failures:{$providerId}");
    }

    private function updateProviderStats(int $providerId, bool $success, int $responseTimeMs): void
    {
        $provider = Provider::find($providerId);
        if (!$provider) return;

        // Rolling average
        $totalRequests = ($provider->total_requests ?? 0) + 1;
        $successCount  = $success
            ? (($provider->success_rate / 100) * ($totalRequests - 1)) + 1
            : (($provider->success_rate / 100) * ($totalRequests - 1));

        $provider->success_rate    = ($successCount / $totalRequests) * 100;
        $provider->failure_rate    = 100 - $provider->success_rate;
        $provider->avg_response_time = $responseTimeMs > 0
            ? (((float)$provider->avg_response_time + $responseTimeMs) / 2)
            : $provider->avg_response_time;

        $provider->save();
    }

    private function providerSupportsNetwork(Provider $provider, string $serviceType, string $network): bool
    {
        $service = $provider->services()
            ->where('service_type', $serviceType)
            ->where('network', $network)
            ->where('status', 'active')
            ->first();

        return $service !== null;
    }
}
