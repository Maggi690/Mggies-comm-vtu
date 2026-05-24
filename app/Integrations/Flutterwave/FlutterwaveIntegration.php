<?php

namespace App\Integrations\Flutterwave;

use App\Exceptions\PaymentException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class FlutterwaveIntegration
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
        $this->baseUrl   = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
    }

    public function initializePayment(User $user, float $amount, string $reference, string $callbackUrl): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/payments", [
                'tx_ref'          => $reference,
                'amount'          => $amount,
                'currency'        => 'NGN',
                'redirect_url'    => $callbackUrl,
                'customer'        => [
                    'email'       => $user->email,
                    'phonenumber' => $user->phone,
                    'name'        => $user->full_name,
                ],
                'customizations'  => [
                    'title'  => 'Universal VTU Pro',
                    'description' => 'Wallet Funding',
                ],
                'payment_options' => 'card,banktransfer,ussd',
                'meta'            => ['user_id' => $user->id],
            ]);

        if (!$response->successful() || $response->json('status') !== 'success') {
            throw new PaymentException('Failed to initialize Flutterwave payment: ' . $response->json('message'));
        }

        return [
            'gateway'      => 'flutterwave',
            'reference'    => $reference,
            'checkout_url' => $response->json('data.link'),
            'amount'       => $amount,
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions", ['tx_ref' => $reference]);

        if (!$response->successful()) {
            throw new PaymentException("Flutterwave verification failed for: {$reference}");
        }

        $data = collect($response->json('data'))->first();

        if (!$data) {
            return ['gateway' => 'flutterwave', 'reference' => $reference, 'status' => 'failed'];
        }

        return [
            'gateway'    => 'flutterwave',
            'reference'  => $reference,
            'status'     => $data['status'] === 'successful' ? 'successful' : 'failed',
            'amount'     => $data['amount'],
            'currency'   => $data['currency'],
            'paid_at'    => $data['created_at'] ?? null,
            'channel'    => $data['payment_type'],
            'user_id'    => $data['meta']['user_id'] ?? null,
            'raw'        => $data,
        ];
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, config('services.flutterwave.webhook_secret'));
        return hash_equals($expected, $signature);
    }
}
