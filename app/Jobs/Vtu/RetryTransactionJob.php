<?php

namespace App\Jobs\Vtu;

use App\DTOs\Vtu\AirtimeDTO;
use App\DTOs\Vtu\CableDTO;
use App\DTOs\Vtu\DataDTO;
use App\DTOs\Vtu\ElectricityDTO;
use App\DTOs\Vtu\ExamDTO;
use App\Models\Transaction;
use App\Services\Vtu\AirtimeService;
use App\Services\Vtu\CableService;
use App\Services\Vtu\DataService;
use App\Services\Vtu\ElectricityService;
use App\Services\Vtu\ExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 90;

    public function __construct(private readonly Transaction $transaction)
    {
        $this->onQueue('vtu');
    }

    public function handle(): void
    {
        $txn = $this->transaction;

        if ($txn->status !== 'failed') {
            Log::warning("RetryTransactionJob: Transaction {$txn->reference} is not in failed state. Skipping.");
            return;
        }

        try {
            match ($txn->service_type) {
                'airtime'     => $this->retryAirtime($txn),
                'data'        => $this->retryData($txn),
                'cable'       => $this->retryCable($txn),
                'electricity' => $this->retryElectricity($txn),
                'exam'        => $this->retryExam($txn),
                default       => Log::warning("RetryTransactionJob: Unknown service type {$txn->service_type}"),
            };
        } catch (\Exception $e) {
            Log::error("RetryTransactionJob failed for {$txn->reference}: " . $e->getMessage());
        }
    }

    private function retryAirtime(Transaction $txn): void
    {
        $dto = AirtimeDTO::fromArray([
            'network'   => $txn->request_data['network'],
            'phone'     => $txn->phone,
            'amount'    => $txn->amount,
            'reference' => 'RETRY-' . $txn->reference,
        ], $txn->user_id);

        app(AirtimeService::class)->purchase($dto);
    }

    private function retryData(Transaction $txn): void
    {
        $dto = DataDTO::fromArray([
            'network'   => $txn->request_data['network'],
            'phone'     => $txn->phone,
            'plan_id'   => $txn->request_data['plan_id'],
            'amount'    => $txn->amount,
            'reference' => 'RETRY-' . $txn->reference,
        ], $txn->user_id);

        app(DataService::class)->purchase($dto);
    }

    private function retryCable(Transaction $txn): void
    {
        $dto = CableDTO::fromArray([
            'provider'         => $txn->request_data['provider'],
            'smartcard_number' => $txn->beneficiary,
            'package_code'     => $txn->request_data['package_code'],
            'amount'           => $txn->amount,
            'phone'            => $txn->phone,
            'reference'        => 'RETRY-' . $txn->reference,
        ], $txn->user_id);

        app(CableService::class)->purchase($dto);
    }

    private function retryElectricity(Transaction $txn): void
    {
        $dto = ElectricityDTO::fromArray([
            'disco'        => $txn->request_data['disco'],
            'meter_number' => $txn->beneficiary,
            'meter_type'   => $txn->request_data['meter_type'],
            'amount'       => $txn->amount,
            'phone'        => $txn->phone,
            'reference'    => 'RETRY-' . $txn->reference,
        ], $txn->user_id);

        app(ElectricityService::class)->purchase($dto);
    }

    private function retryExam(Transaction $txn): void
    {
        $dto = ExamDTO::fromArray([
            'exam_type' => $txn->request_data['exam_type'],
            'quantity'  => $txn->request_data['quantity'] ?? 1,
            'amount'    => $txn->amount,
            'reference' => 'RETRY-' . $txn->reference,
        ], $txn->user_id);

        app(ExamService::class)->purchase($dto);
    }
}
