<?php

namespace App\Integrations\Paga;

use App\Exceptions\PaymentException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class PagaIntegration
{
    private string $apiKey;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey    = config('services.paga.api_key');
        $this->secretKey = config('services.paga.secret_key');
        $this->baseUrl   = config('services.paga.base_url', 'https://www.mypaga.com');
    }

    public function createAccount(User $user): array
    {
        $hash = hash('sha512', $user->id . $user->email . $user->phone . $this->secretKey);

        $response = Http::withHeaders([
            'principal'     => $this->apiKey,
            'credentials'   => $hash,
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/paga-webservices/business-rest/secured/registerPersistentPaymentAccount", [
            'referenceNumber'   => 'UVTP-' . $user->id,
            'phoneNumber'       => $user->phone,
            'firstName'         => $user->first_name,
            'lastName'          => $user->last_name,
            'accountName'       => $user->full_name,
            'financialIdentificationNumber' => $user->bvn ?? '',
        ]);

        if (!$response->successful()) {
            throw new PaymentException('Failed to create Paga virtual account.');
        }

        $data = $response->json();

        return [
            'account_number'    => $data['accountNumber'] ?? null,
            'account_name'      => $user->full_name,
            'bank_name'         => 'Paga',
            'reference'         => 'UVTP-' . $user->id,
        ];
    }
}
