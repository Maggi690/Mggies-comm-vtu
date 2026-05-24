<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Provider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name','slug','api_key','secret_key','endpoint','webhook_secret',
        'status','priority','success_rate','failure_rate','avg_response_time',
        'services','meta','is_default',
    ];

    protected $hidden = ['api_key','secret_key','webhook_secret'];

    protected $casts = [
        'services'      => 'array',
        'meta'          => 'array',
        'is_default'    => 'boolean',
        'success_rate'  => 'decimal:2',
        'failure_rate'  => 'decimal:2',
    ];

    public function setApiKeyAttribute($value)   { $this->attributes['api_key']    = $value ? Crypt::encryptString($value) : null; }
    public function setSecretKeyAttribute($value) { $this->attributes['secret_key'] = $value ? Crypt::encryptString($value) : null; }
    public function setWebhookSecretAttribute($value) { $this->attributes['webhook_secret'] = $value ? Crypt::encryptString($value) : null; }
    public function getApiKeyAttribute($value)   { return $value ? Crypt::decryptString($value) : null; }
    public function getSecretKeyAttribute($value) { return $value ? Crypt::decryptString($value) : null; }
    public function getWebhookSecretAttribute($value) { return $value ? Crypt::decryptString($value) : null; }

    public function services()     { return $this->hasMany(ProviderService::class); }
    public function logs()         { return $this->hasMany(ProviderLog::class); }
    public function balances()     { return $this->hasMany(ProviderBalance::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function supportsService(string $service): bool { return in_array($service, $this->services ?? []); }
}
