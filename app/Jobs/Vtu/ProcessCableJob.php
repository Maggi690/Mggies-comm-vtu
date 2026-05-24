<?php

namespace App\Jobs\Vtu;

use App\DTOs\Vtu\CableDTO;
use App\Services\Vtu\CableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(private readonly CableDTO $dto)
    {
        $this->onQueue('vtu');
    }

    public function handle(CableService $service): void
    {
        try {
            $service->purchase($this->dto);
        } catch (\Exception $e) {
            Log::error('ProcessCableJob failed', ['error' => $e->getMessage()]);
        }
    }
}
