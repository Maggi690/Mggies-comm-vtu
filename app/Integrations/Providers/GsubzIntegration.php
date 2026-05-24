<?php

namespace App\Integrations\Providers;

use App\DTOs\Vtu\AirtimeDTO;
use App\DTOs\Vtu\DataDTO;
use App\Exceptions\VtuException;
use App\Models\DataPlan;
use App\Models\Provider;
use Illuminate\Support\Facades\Http;

class GsubzIntegration
{
    public function purchaseAirtime(Provider $provider, AirtimeDTO $dto): array
    {
        $response = Http::withHeaders(['Authorization' => 'Token ' . $provider->api_key])
            ->timeout(30)
            ->post($provider->endpoint . '/airtime/', [
                'network'    => strtoupper($dto->network),
                'amount'     => $dto->amount,
                'mobile_number' => $dto->phone,
                'Ported_number' => true,
                'airtime_type' => 'VTU',
            ]);

        if (!$response->successful()) {
            throw new VtuException("Gsubz error: " . $response->status());
        }

        $data = $response->json();
        if (($data['Status'] ?? '') !== 'successful') {
            throw new VtuException($data['message'] ?? 'Airtime purchase failed');
        }

        return [
            'provider'           => 'gsubz',
            'provider_reference' => (string)($data['id'] ?? ''),
            'status'             => 'successful',
            'raw'                => $data,
        ];
    }

    public function purchaseData(Provider $provider, DataDTO $dto, DataPlan $plan): array
    {
        $response = Http::withHeaders(['Authorization' => 'Token ' . $provider->api_key])
            ->timeout(30)
            ->post($provider->endpoint . '/data/', [
                'network'     => strtoupper($dto->network),
                'mobile_number' => $dto->phone,
                'plan'        => $plan->provider_plan_id,
                'Ported_number' => true,
            ]);

        if (!$response->successful()) {
            throw new VtuException("Gsubz data error: " . $response->status());
        }

        $data = $response->json();
        if (($data['Status'] ?? '') !== 'successful') {
            throw new VtuException($data['message'] ?? 'Data purchase failed');
        }

        return [
            'provider'           => 'gsubz',
            'provider_reference' => (string)($data['id'] ?? ''),
            'status'             => 'successful',
            'raw'                => $data,
        ];
    }
}
