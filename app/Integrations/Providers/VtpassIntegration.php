<?php

namespace App\Integrations\Providers;

use App\DTOs\Vtu\AirtimeDTO;
use App\DTOs\Vtu\CableDTO;
use App\DTOs\Vtu\DataDTO;
use App\DTOs\Vtu\ElectricityDTO;
use App\DTOs\Vtu\ExamDTO;
use App\Exceptions\VtuException;
use App\Models\DataPlan;
use App\Models\Provider;
use Illuminate\Support\Facades\Http;

class VtpassIntegration
{
    private const NETWORK_MAP = [
        'mtn'    => 'mtn',
        'airtel' => 'airtel',
        'glo'    => 'glo',
        '9mobile'=> 'etisalat',
    ];

    private const DATA_TYPE_MAP = [
        'sme'  => 'mtn-data',
        'sme2' => 'mtn-data2',
        'cg'   => 'mtn-data',
        'cg2'  => 'mtn-data2',
    ];

    private const CABLE_MAP = [
        'dstv'      => 'dstv',
        'gotv'      => 'gotv',
        'startimes' => 'startimes',
    ];

    private const DISCO_MAP = [
        'ekedc'  => 'eko-electric',
        'ikedc'  => 'ikeja-electric',
        'aedc'   => 'abuja-electric',
        'phed'   => 'phed',
        'enedis' => 'enugu-electric',
        'kedco'  => 'kano-electric',
        'jed'    => 'jos-electric',
        'bedc'   => 'benin-electric',
        'eedc'   => 'enugu-electric',
        'ibedc'  => 'ibadan-electric',
    ];

    private const EXAM_MAP = [
        'waec'   => 'waec',
        'neco'   => 'neco',
        'nabteb' => 'nabteb',
        'jamb'   => 'jamb',
    ];

    public function purchaseAirtime(Provider $provider, AirtimeDTO $dto): array
    {
        $serviceId = (self::NETWORK_MAP[$dto->network] ?? $dto->network) . '-airtime';

        $response = $this->post($provider, '/pay', [
            'request_id'     => $dto->reference,
            'serviceID'      => $serviceId,
            'amount'         => $dto->amount,
            'phone'          => $dto->phone,
        ]);

        return $this->parseResponse($response, 'airtime');
    }

    public function purchaseData(Provider $provider, DataDTO $dto, DataPlan $plan): array
    {
        $network   = self::NETWORK_MAP[$dto->network] ?? $dto->network;
        $serviceId = $network . '-data';

        $response = $this->post($provider, '/pay', [
            'request_id'  => $dto->reference,
            'serviceID'   => $serviceId,
            'billersCode' => $dto->phone,
            'variation_code' => $plan->provider_plan_id,
            'amount'      => $plan->amount,
            'phone'       => $dto->phone,
        ]);

        return $this->parseResponse($response, 'data');
    }

    public function validateCableSmartcard(Provider $provider, string $cableProvider, string $smartcardNumber): array
    {
        $serviceId = self::CABLE_MAP[$cableProvider] ?? $cableProvider;
        $response  = $this->post($provider, '/merchant-verify', [
            'billersCode' => $smartcardNumber,
            'serviceID'   => $serviceId,
        ]);

        if (($response['code'] ?? '') !== '000') {
            throw new VtuException("Invalid smartcard number or provider unavailable.");
        }

        return [
            'customer_name'    => $response['content']['Customer_Name'] ?? 'Unknown',
            'current_bouquet'  => $response['content']['Current_Bouquet'] ?? null,
            'due_date'         => $response['content']['Due_Date'] ?? null,
            'smartcard_number' => $smartcardNumber,
        ];
    }

    public function purchaseCable(Provider $provider, CableDTO $dto): array
    {
        $serviceId = self::CABLE_MAP[$dto->provider] ?? $dto->provider;

        $response = $this->post($provider, '/pay', [
            'request_id'     => $dto->reference,
            'serviceID'      => $serviceId,
            'billersCode'    => $dto->smartcardNumber,
            'variation_code' => $dto->packageCode,
            'amount'         => $dto->amount,
            'phone'          => $dto->phone ?? '08000000000',
            'subscription_type' => 'change',
        ]);

        return $this->parseResponse($response, 'cable');
    }

    public function validateMeter(Provider $provider, string $disco, string $meterNumber, string $meterType): array
    {
        $serviceId = self::DISCO_MAP[$disco] ?? $disco;
        $response  = $this->post($provider, '/merchant-verify', [
            'billersCode' => $meterNumber,
            'serviceID'   => $serviceId,
            'type'        => $meterType,
        ]);

        if (($response['code'] ?? '') !== '000') {
            throw new VtuException("Invalid meter number or DISCO unavailable.");
        }

        return [
            'customer_name'    => $response['content']['Customer_Name'] ?? 'Unknown',
            'address'          => $response['content']['Address'] ?? null,
            'meter_number'     => $meterNumber,
            'meter_type'       => $meterType,
            'disco'            => $disco,
            'minimum_amount'   => $response['content']['Minimum_Amount'] ?? 100,
        ];
    }

    public function purchaseElectricity(Provider $provider, ElectricityDTO $dto): array
    {
        $serviceId = self::DISCO_MAP[$dto->disco] ?? $dto->disco;

        $response = $this->post($provider, '/pay', [
            'request_id'  => $dto->reference,
            'serviceID'   => $serviceId,
            'billersCode' => $dto->meterNumber,
            'variation_code' => $dto->meterType,
            'amount'      => $dto->amount,
            'phone'       => $dto->phone ?? '08000000000',
        ]);

        $result = $this->parseResponse($response, 'electricity');

        $result['token']         = $response['token'] ?? $response['purchased_code'] ?? null;
        $result['units']         = $response['units'] ?? null;
        $result['customer_name'] = $response['Customer_Name'] ?? null;
        $result['meter_number']  = $dto->meterNumber;

        return $result;
    }

    public function purchaseExamPin(Provider $provider, ExamDTO $dto): array
    {
        $serviceId = self::EXAM_MAP[$dto->examType] ?? $dto->examType;

        $response = $this->post($provider, '/pay', [
            'request_id'     => $dto->reference,
            'serviceID'      => $serviceId,
            'variation_code' => 'waec_pinscard',
            'amount'         => $dto->amount,
            'quantity'       => $dto->quantity,
            'phone'          => '08000000000',
        ]);

        $result         = $this->parseResponse($response, 'exam');
        $result['pins'] = $response['cards'] ?? [];

        return $result;
    }

    // ─── Private ────────────────────────────────────────────────────────────────

    private function post(Provider $provider, string $endpoint, array $payload): array
    {
        $response = Http::withBasicAuth($provider->api_key, $provider->secret_key)
            ->timeout(30)
            ->post($provider->endpoint . $endpoint, $payload);

        if (!$response->successful()) {
            throw new VtuException("VTpass API error: " . $response->status());
        }

        return $response->json();
    }

    private function parseResponse(array $response, string $service): array
    {
        $code    = $response['code'] ?? $response['response_description'] ?? '';
        $content = $response['content'] ?? $response;

        // VTpass success codes
        $successCodes = ['000', '099'];

        if (!in_array($code, $successCodes)) {
            $error = $response['response_description'] ?? $response['message'] ?? "Transaction failed (code: {$code})";
            throw new VtuException($error);
        }

        return [
            'provider'           => 'vtpass',
            'provider_reference' => $response['requestId'] ?? null,
            'status'             => 'successful',
            'transaction_id'     => $content['transactions']['transactionId'] ?? null,
            'amount'             => $content['transactions']['amount'] ?? null,
            'code'               => $code,
            'raw'                => $response,
        ];
    }
}
