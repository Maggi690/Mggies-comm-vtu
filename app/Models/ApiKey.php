<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id','name','public_key','secret_key','ip_whitelist',
        'allowed_services','status','last_used_at','daily_limit','monthly_limit',
        'daily_usage','monthly_usage','webhook_url','webhook_secret',
    ];

    protected $hidden = ['secret_key'];

    protected $casts = [
        'ip_whitelist'     => 'array',
        'allowed_services' => 'array',
        'last_used_at'     => 'datetime',
        'daily_limit'      => 'integer',
        'monthly_limit'    => 'integer',
    ];

    public function user()         { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function logs()         { return $this->hasMany(ApiKeyLog::class); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ip_whitelist)) return true;
        return in_array($ip, $this->ip_whitelist);
    }
}
