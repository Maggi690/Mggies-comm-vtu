<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'ulid'               => $this->ulid,
            'reference'          => $this->reference,
            'type'               => $this->type,
            'service_type'       => $this->service_type,
            'amount'             => (float) $this->amount,
            'fee'                => (float) $this->fee,
            'status'             => $this->status,
            'phone'              => $this->phone,
            'beneficiary'        => $this->beneficiary,
            'description'        => $this->description,
            'provider_reference' => $this->provider_reference,
            'retries'            => $this->retries,
            'response_data'      => $this->when(
                $this->status === 'successful',
                fn() => $this->extractPublicResponseData()
            ),
            'provider'           => $this->whenLoaded('provider', fn() => [
                'id'   => $this->provider?->id,
                'name' => $this->provider?->name,
            ]),
            'user'               => $this->whenLoaded('user', fn() => [
                'id'    => $this->user?->id,
                'name'  => $this->user?->full_name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ]),
            'settled_at'         => $this->settled_at?->toIso8601String(),
            'refunded_at'        => $this->refunded_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }

    private function extractPublicResponseData(): ?array
    {
        if (!$this->response_data) return null;

        // Return only customer-safe data (exclude internal provider raw)
        $safe = [];
        $publicKeys = ['token', 'units', 'customer_name', 'meter_number', 'pins', 'transaction_id'];
        foreach ($publicKeys as $key) {
            if (isset($this->response_data[$key])) {
                $safe[$key] = $this->response_data[$key];
            }
        }
        return !empty($safe) ? $safe : null;
    }
}
