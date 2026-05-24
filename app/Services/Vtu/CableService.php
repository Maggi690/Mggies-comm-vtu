<?php

namespace App\Services\Vtu;

use App\DTOs\Vtu\CableDTO;
use App\Exceptions\VtuException;
use App\Models\Transaction;
use App\Services\Providers\ProviderRoutingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;

class CableService
{
    public function __construct(
        private readonly ProviderRoutingService $routingService,
        private readonly WalletService $walletService,
    ) {}

    public function validateSmartcard(string $provider, string $smartcardNumber): array
    {
        $routingProvider = $this->routingService->getProviderForService('cable');
        $integration = $this->resolveIntegration($routingProvider->slug);
        return app($integration)->validateCableSmartcard($routingProvider, $provider, $smartcardNumber);
    }

    public function purchase(CableDTO $dto): Transaction
    {
        $this->validateProvider($dto->provider);

        $transaction = Transaction::create([
            'user_id'      => $dto->userId,
            'ulid'         => Str::ulid(),
            'reference'    => $dto->reference,
            'type'         => 'debit',
            'service_type' => 'cable',
            'amount'       => $dto->amount,
            'status'       => 'pending',
            'beneficiary'  => $dto->smartcardNumber,
            'phone'        => $dto->phone,
            'description'  => "Cable TV - {$dto->provider} - {$dto->smartcardNumber}",
            'request_data' => [
                'provider'         => $dto->provider,
                'smartcard_number' => $dto->smartcardNumber,
                'package_code'     => $dto->packageCode,
            ],
            'api_key_id'   => $dto->apiKeyId,
        ]);

        try {
            $this->walletService->debitInternal(
                $dto->userId, $dto->amount, $dto->reference,
                "Cable TV - {$dto->provider} - {$dto->smartcardNumber}",
                ['transaction_id' => $transaction->id],
            );
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed', 'response_data' => ['error' => $e->getMessage()]]);
            throw $e;
        }

        try {
            $provider    = $this->routingService->getProviderForService('cable');
            $integration = $this->resolveIntegration($provider->slug);
            $result      = app($integration)->purchaseCable($provider, $dto);

            $transaction->update([
                'provider_id'        => $provider->id,
                'status'             => 'successful',
                'provider_reference' => $result['provider_reference'] ?? null,
                'response_data'      => $result,
                'settled_at'         => now(),
            ]);

        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed', 'response_data' => ['error' => $e->getMessage()]]);
            $this->walletService->refund($dto->userId, $dto->amount, $dto->reference, $dto->reference);
            throw new VtuException("Cable TV subscription failed. Your wallet has been refunded.");
        }

        return $transaction->fresh();
    }

    private function validateProvider(string $provider): void
    {
        $supported = ['dstv', 'gotv', 'startimes'];
        if (!in_array($provider, $supported)) {
            throw new VtuException("Unsupported cable provider: {$provider}");
        }
    }

    private function resolveIntegration(string $slug): string
    {
        return match ($slug) {
            'vtpass' => \App\Integrations\Providers\VtpassIntegration::class,
            default  => throw new VtuException("Unknown provider: {$slug}"),
        };
    }
}
