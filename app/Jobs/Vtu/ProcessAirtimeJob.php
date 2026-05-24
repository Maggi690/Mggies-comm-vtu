<?php

namespace App\Jobs\Vtu;

use App\DTOs\Vtu\AirtimeDTO;
use App\Services\Vtu\AirtimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAirtimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // AirtimeService handles its own retries
    public int $timeout = 60;

    public function __construct(private readonly AirtimeDTO $dto)
    {
        $this->onQueue('vtu');
    }

    public function handle(AirtimeService $airtimeService): void
    {
        try {
            $airtimeService->purchase($this->dto);
        } catch (\Exception $e) {
            Log::error('ProcessAirtimeJob failed', [
                'reference' => $this->dto->reference,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
