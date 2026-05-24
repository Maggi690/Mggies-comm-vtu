<?php

namespace App\Services\Vtu;

use App\DTOs\Vtu\ElectricityDTO;
use App\Exceptions\VtuException;
use App\Models\Transaction;
use App\Services\Providers\ProviderRoutingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;

class ElectricityService
{
    private const SUPPORTED_DISCOS = [
        'ekedc','ikedc','aedc','phed','enedis','kedco','jed','bedc','eedc','ibedc',
    ];

    public function __construct(
        private readonly ProviderRoutingService $routingService,
        private readonly WalletService $walletService,
    ) {}

    public function validateMeter(string $disco, string $meterNumber, string $meterType): array
    {
        $this->validateDisco($disco);
        $provider    = $this->routingService->getProviderForService('electricity');
        $integration = $this->resolveIntegration($provider->slug);
        return app($integration)->validateMeter($provider, $disco, $meterNumber, $meterType);
    }

    public function purchase(ElectricityDTO $dto): Transaction
    {
        $this->validateDisco($dto->disco);

        if ($dto->amount < 100) {
            throw new VtuException("Minimum electricity purchase is ₦100.");
        }

        $transaction = Transaction::create([
            'user_id'      => $dto->userId,
            'ulid'         => Str::ulid(),
            'reference'    => $dto->reference,
            'type'         => 'debit',
            'service_type' => 'electricity',
            'amount'       => $dto->amount,
            'status'       => 'pending',
            'beneficiary'  => $dto->meterNumber,
            'phone'        => $dto->phone,
            'description'  => "Electricity - {$dto->disco} - Meter {$dto->meterNumber}",
            'request_data' => [
                'disco'        => $dto->disco,
                'meter_number' => $dto->meterNumber,
                'meter_type'   => $dto->meterType,
                'amount'       => $dto->amount,
            ],
            'api_key_id'   => $dto->apiKeyId,
        ]);

        try {
            $this->walletService->debitInternal(
                $dto->userId, $dto->amount, $dto->reference,
                "Electricity - {$dto->disco} - {$dto->meterNumber}",
                ['transaction_id' => $transaction->id],
            );
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            throw $e;
        }

        try {
            $provider    = $this->routingService->getProviderForService('electricity');
            $integration = $this->resolveIntegration($provider->slug);
            $result      = app($integration)->purchaseElectricity($provider, $dto);

            $transaction->update([
                'provider_id'        => $provider->id,
                'status'             => 'successful',
                'provider_reference' => $result['provider_reference'] ?? null,
                'response_data'      => array_merge($result, [
                    'token'           => $result['token'] ?? null,
                    'units'           => $result['units'] ?? null,
                    'customer_name'   => $result['customer_name'] ?? null,
                    'meter_number'    => $dto->meterNumber,
                ]),
                'settled_at' => now(),
            ]);

        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed', 'response_data' => ['error' => $e->getMessage()]]);
            $this->walletService->refund($dto->userId, $dto->amount, $dto->reference, $dto->reference);
            throw new VtuException("Electricity purchase failed. Your wallet has been refunded.");
        }

        return $transaction->fresh();
    }

    private function validateDisco(string $disco): void
    {
        if (!in_array($disco, self::SUPPORTED_DISCOS)) {
            throw new VtuException("Unsupported DISCO: {$disco}. Supported: " . implode(', ', self::SUPPORTED_DISCOS));
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
