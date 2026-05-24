<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id','ulid','balance','ledger_balance','frozen_balance',
        'currency','status','last_transaction_at',
    ];

    protected $casts = [
        'balance'          => 'decimal:2',
        'ledger_balance'   => 'decimal:2',
        'frozen_balance'   => 'decimal:2',
        'last_transaction_at' => 'datetime',
    ];

    public function user()         { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(WalletTransaction::class); }
    public function holds()        { return $this->hasMany(WalletHold::class); }
    public function refunds()      { return $this->hasMany(WalletRefund::class); }

    public function getAvailableBalanceAttribute(): float
    {
        return (float) ($this->balance - $this->frozen_balance);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }
}
