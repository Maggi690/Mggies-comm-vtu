<?php

namespace App\Jobs\Vtu;

use App\DTOs\Vtu\ExamDTO;
use App\Services\Vtu\ExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(private readonly ExamDTO $dto)
    {
        $this->onQueue('vtu');
    }

    public function handle(ExamService $service): void
    {
        try {
            $service->purchase($this->dto);
        } catch (\Exception $e) {
            Log::error('ProcessExamJob failed', ['error' => $e->getMessage()]);
        }
    }
}
