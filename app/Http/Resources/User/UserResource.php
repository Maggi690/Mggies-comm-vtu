<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'ulid'               => $this->ulid,
            'first_name'         => $this->first_name,
            'last_name'          => $this->last_name,
            'full_name'          => $this->full_name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'username'           => $this->username,
            'user_type'          => $this->user_type,
            'status'             => $this->status,
            'kyc_status'         => $this->kyc_status,
            'email_verified'     => !is_null($this->email_verified_at),
            'phone_verified'     => !is_null($this->phone_verified_at),
            'two_factor_enabled' => $this->two_factor_enabled,
            'api_access_enabled' => $this->api_access_enabled,
            'referral_code'      => $this->referral_code,
            'avatar'             => $this->avatar,
            'last_login_at'      => $this->last_login_at?->toIso8601String(),
            'roles'              => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'wallet'             => $this->whenLoaded('wallet', fn() => [
                'balance'           => (float) $this->wallet?->balance,
                'available_balance' => (float) $this->wallet?->available_balance,
                'ledger_balance'    => (float) $this->wallet?->ledger_balance,
                'frozen_balance'    => (float) $this->wallet?->frozen_balance,
                'currency'          => $this->wallet?->currency,
            ]),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
