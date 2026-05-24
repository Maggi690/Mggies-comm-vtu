<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderService extends Model
{
    protected $fillable = ['provider_id','service_type','network','fee_type','fee_value','min_amount','max_amount','status','meta'];
    protected $casts = ['fee_value' => 'decimal:4','min_amount' => 'decimal:2','max_amount' => 'decimal:2','meta' => 'array'];
    public function provider() { return $this->belongsTo(Provider::class); }
}
