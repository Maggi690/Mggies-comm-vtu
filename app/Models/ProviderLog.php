<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderLog extends Model
{
    protected $fillable = ['provider_id','transaction_id','action','request','response','status','response_time_ms','error'];
    protected $casts = ['request' => 'array','response' => 'array'];
    public function provider()    { return $this->belongsTo(Provider::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
}
