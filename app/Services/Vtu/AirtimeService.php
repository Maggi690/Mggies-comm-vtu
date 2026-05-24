<?php

namespace App\Services\Vtu;

use App\DTOs\Vtu\AirtimeDTO;
use App\Events\Vtu\AirtimePurchased;
use App\Events\Vtu\AirtimeFailed;
use App\Exceptions\VtuException;
use App\Models\Transaction;
use App\Services\Providers\ProviderRoutingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AirtimeService
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly ProviderRoutingService $routingService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Purchase airtime with automatic failover and refund on failure.
     */
    public function purchase(AirtimeDTO $dto): Transaction
    {
        $this->validateNetwork($dto->network);

        // Create pending transaction record first
        $transaction = $this->createTransaction($dto);

        // Debit wallet
        try {
            $this->walletService->debitInternal(
                userId:      $dto->userId,
                amount:      $dto->amount,
                reference:   $dto->reference,
                description: "Airtime purchase - {$dto->network} - {$dto->phone}",
                meta:        ['transaction_id' => $transaction->id],
            );
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed', 'response_data' => ['error' => $e->getMessage()]]);
            throw $e;
        }

        // Attempt with provider routing and failover
        $attempt     = 0;
        $lastError   = null;
        $usedProviders = [];

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                $provider = $this->routingService->getProviderForService('airtime', $dto->network);

                // Skip already-tried providers
                if (in_array($provider->id, $usedProviders)) {
                    break;
                }

                $usedProviders[] = $provider->id;

                $transaction->update([
                    'provider_id' => $provider->id,
                    'retries'     => $attempt - 1,
                ]);

                $startTime = microtime(true);

                // Execute the purchase via the provider integration
                $result = $this->executeWithProvider($provider, $dto);

                $responseTime = (int)((microtime(true) - $startTime) * 1000);

                // Log success
                $this->routingService->logInteraction(
                    $provider->id, $transaction->id, 'airtime_purchase',
                    $dto->meta, $result, 'success', $responseTime
                );
                $this->routingService->recordSuccess($provider->id, 'airtime', $responseTime);

                $transaction->update([
                    'status'              => 'successful',
                    'provider_reference'  => $result['provider_reference'] ?? null,
                    'provider_response'   => $result,
                    'response_data'       => $result,
                    'settled_at'          => now(),
                ]);

                event(new AirtimePurchased($transaction));

                return $transaction->fresh();

            } catch (VtuException $e) {
                $lastError = $e;
                Log::warning("Airtime purchase attempt {$attempt} failed", [
                    'provider_id' => $provider->id ?? null,
                    'error'       => $e->getMessage(),
                    'reference'   => $dto->reference,
                ]);

                if (isset($provider)) {
                    $this->routingService->recordFailure($provider->id, 'airtime', $e->getMessage());
                    $this->routingService->logInteraction(
                        $provider->id, $transaction->id, 'airtime_purchase',
                        $dto->meta, ['error' => $e->getMessage()], 'failed', 0, $e->getMessage()
                    );
                }

                $transaction->update([
                    'last_retry_at' => now(),
                    'retries'       => $attempt,
                ]);
            } catch (\Exception $e) {
                $lastError = $e;
                Log::error("Unexpected error on airtime attempt {$attempt}", ['error' => $e->getMessage()]);
                break;
            }
        }

        // All attempts failed — refund the user
        $transaction->update([
            'status'        => 'failed',
            'response_data' => ['error' => $lastError?->getMessage() ?? 'All providers failed'],
        ]);

        $this->walletService->refund($dto->userId, $dto->amount, $dto->reference, $dto->reference);

        event(new AirtimeFailed($transaction));

        throw new VtuException("Airtime purchase failed after {$attempt} attempts. Your wallet has been refunded.");
    }

    public function getStatus(string $transactionId): Transaction
    {
        return Transaction::where('ulid', $transactionId)
            ->orWhere('reference', $transactionId)
            ->where('service_type', 'airtime')
            ->firstOrFail();
    }

    // ─── Private ────────────────────────────────────────────────────────────────

    private function createTransaction(AirtimeDTO $dto): Transaction
    {
        return Transaction::create([
            'user_id'      => $dto->userId,
            'ulid'         => Str::ulid(),
            'reference'    => $dto->reference,
            'type'         => 'debit',
            'service_type' => 'airtime',
            'amount'       => $dto->amount,
            'fee'          => 0,
            'status'       => 'pending',
            'phone'        => $dto->phone,
            'beneficiary'  => $dto->phone,
            'description'  => "Airtime {$dto->network} - {$dto->phone}",
            'request_data' => [
                'network' => $dto->network,
                'phone'   => $dto->phone,
                'amount'  => $dto->amount,
            ],
            'api_key_id'   => $dto->apiKeyId,
        ]);
    }

    private function executeWithProvider(\App\Models\Provider $provider, AirtimeDTO $dto): array
    {
        $integrationClass = $this->resolveIntegration($provider->slug);
        $integration      = app($integrationClass);
        return $integration->purchaseAirtime($provider, $dto);
    }

    private function resolveIntegration(string $slug): string
    {
        return match ($slug) {
            'vtpass'    => \App\Integrations\Providers\VtpassIntegration::class,
            'husmodata' => \App\Integrations\Providers\HusmodataIntegration::class,
            'gsubz'     => \App\Integrations\Providers\GsubzIntegration::class,
            default     => throw new VtuException("Unknown provider: {$slug}"),
        };
    }

    private function validateNetwork(string $network): void
    {
        $supported = ['mtn', 'airtel', 'glo', '9mobile'];
        if (!in_array($network, $supported)) {
            throw new VtuException("Unsupported network: {$network}. Supported: " . implode(', ', $supported));
        }
    }
}
