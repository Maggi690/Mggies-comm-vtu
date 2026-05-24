<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'ulid'        => $this->ulid,
            'type'        => $this->type,
            'amount'      => (float) $this->amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after'  => (float) $this->balance_after,
            'reference'   => $this->reference,
            'description' => $this->description,
            'status'      => $this->status,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
