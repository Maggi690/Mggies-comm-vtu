<?php

namespace App\Jobs\Webhook;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60, 300, 600];

    public function __construct(
        private readonly string $url,
        private readonly array $payload,
        private readonly ?string $secret = null,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $body      = json_encode($this->payload);
        $signature = $this->secret ? hash_hmac('sha256', $body, $this->secret) : null;

        $headers = ['Content-Type' => 'application/json'];
        if ($signature) {
            $headers['X-Webhook-Signature'] = $signature;
        }

        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->post($this->url, $this->payload);

        if (!$response->successful()) {
            Log::warning("Webhook delivery failed: {$this->url}", ['status' => $response->status()]);
            $this->fail(new \Exception("Webhook delivery failed with status {$response->status()}"));
        }

        Log::info("Webhook delivered to {$this->url}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Webhook permanently failed: {$this->url}", ['error' => $exception->getMessage()]);

        \App\Models\WebhookLog::create([
            'gateway'    => 'developer',
            'payload'    => $this->payload,
            'status'     => 'failed',
            'error'      => $exception->getMessage(),
            'ip_address' => 'system',
        ]);
    }
}
