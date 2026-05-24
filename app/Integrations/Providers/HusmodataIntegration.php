<?php

namespace App\Integrations\Providers;

use App\DTOs\Vtu\AirtimeDTO;
use App\DTOs\Vtu\DataDTO;
use App\Exceptions\VtuException;
use App\Models\DataPlan;
use App\Models\Provider;
use Illuminate\Support\Facades\Http;

class HusmodataIntegration
{
    public function purchaseAirtime(Provider $provider, AirtimeDTO $dto): array
    {
        $response = Http::withHeaders(['Authorization' => 'Token ' . $provider->api_key])
            ->timeout(30)
            ->post($provider->endpoint . '/topup/', [
                'network'        => strtoupper($dto->network),
                'mobile_number'  => $dto->phone,
                'amount'         => $dto->amount,
                'Ported_number'  => true,
                'airtime_type'   => 'VTU',
            ]);

        if (!$response->successful()) {
            throw new VtuException("Husmodata error: " . $response->status());
        }

        $data = $response->json();
        if (($data['Status'] ?? '') !== 'successful') {
            throw new VtuException($data['api_response'] ?? 'Airtime purchase failed');
        }

        return [
            'provider'           => 'husmodata',
            'provider_reference' => (string)($data['ident'] ?? ''),
            'status'             => 'successful',
            'raw'                => $data,
        ];
    }

    public function purchaseData(Provider $provider, DataDTO $dto, DataPlan $plan): array
    {
        $networkMap = ['mtn' => '1', 'glo' => '2', 'airtel' => '3', '9mobile' => '4'];
        $networkId  = $networkMap[$dto->network] ?? '1';

        $response = Http::withHeaders(['Authorization' => 'Token ' . $provider->api_key])
            ->timeout(30)
            ->post($provider->endpoint . '/data/', [
                'network'     => $networkId,
                'mobile_number' => $dto->phone,
                'plan'        => $plan->provider_plan_id,
                'Ported_number' => true,
            ]);

        if (!$response->successful()) {
            throw new VtuException("Husmodata data error: " . $response->status());
        }

        $data = $response->json();
        if (($data['Status'] ?? '') !== 'successful') {
            throw new VtuException($data['api_response'] ?? 'Data purchase failed');
        }

        return [
            'provider'           => 'husmodata',
            'provider_reference' => (string)($data['ident'] ?? ''),
            'status'             => 'successful',
            'raw'                => $data,
        ];
    }
}
