<?php

namespace App\Integrations\Monnify;

use App\Exceptions\PaymentException;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonnifyIntegration
{
    private string $apiKey;
    private string $secretKey;
    private string $contractCode;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey       = config('services.monnify.api_key');
        $this->secretKey    = config('services.monnify.secret_key');
        $this->contractCode = config('services.monnify.contract_code');
        $this->baseUrl      = config('services.monnify.base_url', 'https://api.monnify.com');
    }

    public function initializePayment(User $user, float $amount, string $reference, string $callbackUrl): array
    {
        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/v1/merchant/transactions/init-transaction", [
                'amount'                => $amount,
                'customerName'          => $user->full_name,
                'customerEmail'         => $user->email,
                'paymentReference'      => $reference,
                'paymentDescription'    => "Wallet Funding",
                'currencyCode'          => 'NGN',
                'contractCode'          => $this->contractCode,
                'redirectUrl'           => $callbackUrl,
                'paymentMethods'        => ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'],
            ]);

        if (!$response->successful() || !$response->json('requestSuccessful')) {
            Log::error('Monnify init failed', $response->json());
            throw new PaymentException('Failed to initialize payment. Please try again.');
        }

        $data = $response->json('responseBody');
        return [
            'gateway'          => 'monnify',
            'reference'        => $reference,
            'checkout_url'     => $data['checkoutUrl'],
            'transaction_ref'  => $data['transactionReference'],
            'amount'           => $amount,
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $token    = $this->getAccessToken();
        $encoded  = urlencode($reference);
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/api/v2/transactions/{$encoded}");

        if (!$response->successful()) {
            throw new PaymentException("Payment verification failed for reference: {$reference}");
        }

        $data = $response->json('responseBody');

        return [
            'gateway'    => 'monnify',
            'reference'  => $reference,
            'status'     => $data['paymentStatus'] === 'PAID' ? 'successful' : 'failed',
            'amount'     => $data['amountPaid'] ?? 0,
            'currency'   => 'NGN',
            'paid_at'    => $data['completedOn'] ?? null,
            'channel'    => $data['paymentMethod'] ?? null,
            'raw'        => $data,
        ];
    }

    public function createReservedAccount(User $user): array
    {
        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/v2/bank-transfer/reserved-accounts", [
                'accountReference'   => 'UVTP-' . $user->id,
                'accountName'        => $user->full_name,
                'currencyCode'       => 'NGN',
                'contractCode'       => $this->contractCode,
                'customerEmail'      => $user->email,
                'customerName'       => $user->full_name,
                'customerBvn'        => $user->bvn ?? '',
                'getAllAvailableBanks' => true,
            ]);

        if (!$response->successful() || !$response->json('requestSuccessful')) {
            throw new PaymentException('Failed to create reserved account.');
        }

        $data = $response->json('responseBody');
        return [
            'account_reference' => $data['accountReference'],
            'accounts'          => $data['accounts'],
        ];
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $payload, config('services.monnify.webhook_secret'));
        return hash_equals($expected, $signature);
    }

    private function getAccessToken(): string
    {
        return \Cache::remember('monnify:access_token', 50 * 60, function () {
            $credentials = base64_encode("{$this->apiKey}:{$this->secretKey}");
            $response    = Http::withHeaders(['Authorization' => "Basic {$credentials}"])
                ->post("{$this->baseUrl}/api/v1/auth/login");

            if (!$response->successful() || !$response->json('requestSuccessful')) {
                throw new PaymentException('Failed to authenticate with Monnify.');
            }

            return $response->json('responseBody.accessToken');
        });
    }
}
