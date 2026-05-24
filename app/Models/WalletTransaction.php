<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id','ulid','type','amount','balance_before','balance_after',
        'ledger_balance_before','ledger_balance_after','reference',
        'description','meta','status','transactable_type','transactable_id',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'balance_before'         => 'decimal:2',
        'balance_after'          => 'decimal:2',
        'ledger_balance_before'  => 'decimal:2',
        'ledger_balance_after'   => 'decimal:2',
        'meta'                   => 'array',
    ];

    public function wallet()       { return $this->belongsTo(Wallet::class); }
    public function transactable() { return $this->morphTo(); }
}
