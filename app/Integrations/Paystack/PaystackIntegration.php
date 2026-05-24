<?php

namespace App\Integrations\Paystack;

use App\Exceptions\PaymentException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class PaystackIntegration
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->baseUrl   = config('services.paystack.base_url', 'https://api.paystack.co');
    }

    public function initializePayment(User $user, float $amount, string $reference, string $callbackUrl): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email'        => $user->email,
                'amount'       => (int)($amount * 100), // kobo
                'reference'    => $reference,
                'callback_url' => $callbackUrl,
                'metadata'     => [
                    'user_id'  => $user->id,
                    'name'     => $user->full_name,
                ],
                'channels'     => ['card', 'bank', 'ussd', 'bank_transfer'],
            ]);

        if (!$response->successful() || !$response->json('status')) {
            throw new PaymentException('Failed to initialize Paystack payment: ' . $response->json('message'));
        }

        $data = $response->json('data');
        return [
            'gateway'       => 'paystack',
            'reference'     => $reference,
            'checkout_url'  => $data['authorization_url'],
            'access_code'   => $data['access_code'],
            'amount'        => $amount,
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (!$response->successful() || !$response->json('status')) {
            throw new PaymentException("Paystack verification failed for: {$reference}");
        }

        $data = $response->json('data');
        return [
            'gateway'    => 'paystack',
            'reference'  => $reference,
            'status'     => $data['status'] === 'success' ? 'successful' : 'failed',
            'amount'     => $data['amount'] / 100,
            'currency'   => $data['currency'],
            'paid_at'    => $data['paid_at'] ?? null,
            'channel'    => $data['channel'],
            'user_id'    => $data['metadata']['user_id'] ?? null,
            'raw'        => $data,
        ];
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $payload, $this->secretKey);
        return hash_equals($expected, $signature);
    }
}
