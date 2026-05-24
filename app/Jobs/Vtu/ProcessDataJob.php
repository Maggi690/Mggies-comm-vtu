<?php

namespace App\Jobs\Vtu;

use App\DTOs\Vtu\DataDTO;
use App\Services\Vtu\DataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(private readonly DataDTO $dto)
    {
        $this->onQueue('vtu');
    }

    public function handle(DataService $service): void
    {
        try {
            $service->purchase($this->dto);
        } catch (\Exception $e) {
            Log::error('ProcessDataJob failed', ['error' => $e->getMessage()]);
        }
    }
}
