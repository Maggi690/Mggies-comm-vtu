<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id','ulid','reference','type','service_type','amount','fee',
        'discount','cashback','status','provider_id','provider_reference',
        'provider_response','request_data','response_data','phone',
        'beneficiary','description','retries','last_retry_at','settled_at',
        'refunded_at','ip_address','user_agent','api_key_id',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'fee'               => 'decimal:2',
        'discount'          => 'decimal:2',
        'cashback'          => 'decimal:2',
        'provider_response' => 'array',
        'request_data'      => 'array',
        'response_data'     => 'array',
        'last_retry_at'     => 'datetime',
        'settled_at'        => 'datetime',
        'refunded_at'       => 'datetime',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function provider() { return $this->belongsTo(Provider::class); }
    public function apiKey()   { return $this->belongsTo(ApiKey::class); }
    public function refund()   { return $this->hasOne(WalletRefund::class); }
    public function walletTransaction() { return $this->morphOne(WalletTransaction::class, 'transactable'); }

    public function scopePending($q)    { return $q->where('status', 'pending'); }
    public function scopeSuccessful($q) { return $q->where('status', 'successful'); }
    public function scopeFailed($q)     { return $q->where('status', 'failed'); }
}
