<?php

namespace App\Services\Vtu;

use App\DTOs\Vtu\ExamDTO;
use App\Exceptions\VtuException;
use App\Models\Transaction;
use App\Services\Providers\ProviderRoutingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;

class ExamService
{
    private const SUPPORTED_EXAMS = ['waec','neco','nabteb','jamb'];

    public function __construct(
        private readonly ProviderRoutingService $routingService,
        private readonly WalletService $walletService,
    ) {}

    public function purchase(ExamDTO $dto): Transaction
    {
        $this->validateExamType($dto->examType);

        $transaction = Transaction::create([
            'user_id'      => $dto->userId,
            'ulid'         => Str::ulid(),
            'reference'    => $dto->reference,
            'type'         => 'debit',
            'service_type' => 'exam',
            'amount'       => $dto->amount,
            'status'       => 'pending',
            'description'  => strtoupper($dto->examType) . " Scratch Card (Qty: {$dto->quantity})",
            'request_data' => [
                'exam_type' => $dto->examType,
                'quantity'  => $dto->quantity,
                'amount'    => $dto->amount,
            ],
            'api_key_id' => $dto->apiKeyId,
        ]);

        try {
            $this->walletService->debitInternal(
                $dto->userId, $dto->amount, $dto->reference,
                strtoupper($dto->examType) . " scratch card purchase",
                ['transaction_id' => $transaction->id],
            );
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            throw $e;
        }

        try {
            $provider    = $this->routingService->getProviderForService('exam');
            $integration = $this->resolveIntegration($provider->slug);
            $result      = app($integration)->purchaseExamPin($provider, $dto);

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
            throw new VtuException("Exam pin purchase failed. Your wallet has been refunded.");
        }

        return $transaction->fresh();
    }

    private function validateExamType(string $type): void
    {
        if (!in_array($type, self::SUPPORTED_EXAMS)) {
            throw new VtuException("Unsupported exam type: {$type}. Supported: " . implode(', ', self::SUPPORTED_EXAMS));
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
