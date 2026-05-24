<?php

namespace App\Services\Vtu;

use App\DTOs\Vtu\DataDTO;
use App\Exceptions\VtuException;
use App\Models\DataPlan;
use App\Models\Transaction;
use App\Services\Providers\ProviderRoutingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;

class DataService
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly ProviderRoutingService $routingService,
        private readonly WalletService $walletService,
    ) {}

    public function getPlans(?string $network = null, ?string $planType = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = DataPlan::where('status', 'active');
        if ($network)   $query->where('network', strtolower($network));
        if ($planType)  $query->where('plan_type', strtolower($planType));
        return $query->orderBy('amount')->get();
    }

    public function purchase(DataDTO $dto): Transaction
    {
        $plan = DataPlan::findOrFail($dto->planId);
        $this->validatePlan($plan, $dto);

        $transaction = $this->createTransaction($dto, $plan);

        try {
            $this->walletService->debitInternal(
                $dto->userId, $plan->selling_price, $dto->reference,
                "Data purchase - {$plan->name} - {$dto->phone}",
                ['transaction_id' => $transaction->id],
            );
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed', 'response_data' => ['error' => $e->getMessage()]]);
            throw $e;
        }

        $attempt = 0;
        $usedProviders = [];

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                $provider = $this->routingService->getProviderForService('data', $dto->network);
                if (in_array($provider->id, $usedProviders)) break;
                $usedProviders[] = $provider->id;

                $transaction->update(['provider_id' => $provider->id, 'retries' => $attempt - 1]);

                $startTime = microtime(true);
                $result    = $this->executeWithProvider($provider, $dto, $plan);
                $rt        = (int)((microtime(true) - $startTime) * 1000);

                $this->routingService->recordSuccess($provider->id, 'data', $rt);

                $transaction->update([
                    'status'             => 'successful',
                    'provider_reference' => $result['provider_reference'] ?? null,
                    'response_data'      => $result,
                    'settled_at'         => now(),
                ]);

                return $transaction->fresh();

            } catch (VtuException $e) {
                if (isset($provider)) {
                    $this->routingService->recordFailure($provider->id, 'data', $e->getMessage());
                }
                $transaction->update(['last_retry_at' => now(), 'retries' => $attempt]);
            }
        }

        $transaction->update(['status' => 'failed']);
        $this->walletService->refund($dto->userId, $plan->selling_price, $dto->reference, $dto->reference);

        throw new VtuException("Data purchase failed after {$attempt} attempts. Your wallet has been refunded.");
    }

    public function getStatus(string $transactionId): Transaction
    {
        return Transaction::where('ulid', $transactionId)
            ->orWhere('reference', $transactionId)
            ->where('service_type', 'data')
            ->firstOrFail();
    }

    private function createTransaction(DataDTO $dto, DataPlan $plan): Transaction
    {
        return Transaction::create([
            'user_id'      => $dto->userId,
            'ulid'         => Str::ulid(),
            'reference'    => $dto->reference,
            'type'         => 'debit',
            'service_type' => 'data',
            'amount'       => $plan->selling_price,
            'fee'          => 0,
            'status'       => 'pending',
            'phone'        => $dto->phone,
            'beneficiary'  => $dto->phone,
            'description'  => "Data {$plan->name} - {$dto->phone}",
            'request_data' => [
                'network'   => $dto->network,
                'phone'     => $dto->phone,
                'plan_id'   => $dto->planId,
                'plan_name' => $plan->name,
            ],
            'api_key_id'   => $dto->apiKeyId,
        ]);
    }

    private function validatePlan(DataPlan $plan, DataDTO $dto): void
    {
        if ($plan->network !== $dto->network) {
            throw new VtuException("Plan network mismatch. Expected: {$dto->network}, Got: {$plan->network}");
        }
        if ($plan->status !== 'active') {
            throw new VtuException("Data plan is currently unavailable.");
        }
    }

    private function executeWithProvider(\App\Models\Provider $provider, DataDTO $dto, DataPlan $plan): array
    {
        $integrationClass = match ($provider->slug) {
            'vtpass'    => \App\Integrations\Providers\VtpassIntegration::class,
            'husmodata' => \App\Integrations\Providers\HusmodataIntegration::class,
            'gsubz'     => \App\Integrations\Providers\GsubzIntegration::class,
            default     => throw new VtuException("Unknown provider: {$provider->slug}"),
        };
        return app($integrationClass)->purchaseData($provider, $dto, $plan);
    }
}
