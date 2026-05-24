<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataPlan extends Model
{
    protected $fillable = [
        'provider_id','network','plan_type','plan_id','name','description',
        'size','size_unit','validity','validity_unit','amount','selling_price',
        'provider_plan_id','status','meta',
    ];
    protected $casts = ['amount' => 'decimal:2','selling_price' => 'decimal:2','meta' => 'array'];
    public function provider() { return $this->belongsTo(Provider::class); }
}
