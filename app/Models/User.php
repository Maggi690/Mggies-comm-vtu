<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, LogsActivity;

    protected $fillable = [
        'ulid','first_name','last_name','email','phone','username',
        'password','transaction_pin','user_type','referral_code',
        'referred_by','status','email_verified_at','phone_verified_at',
        'kyc_status','bvn','nin','avatar','device_token',
        'last_login_at','last_login_ip','two_factor_enabled',
        'two_factor_secret','api_access_enabled',
    ];

    protected $hidden = [
        'password','transaction_pin','two_factor_secret','bvn','nin','remember_token',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'phone_verified_at'  => 'datetime',
        'last_login_at'      => 'datetime',
        'two_factor_enabled' => 'boolean',
        'api_access_enabled' => 'boolean',
    ];

    public function wallet()            { return $this->hasOne(Wallet::class); }
    public function transactions()      { return $this->hasMany(Transaction::class); }
    public function referrals()         { return $this->hasMany(Referral::class, 'referrer_id'); }
    public function referredBy()        { return $this->belongsTo(User::class, 'referred_by'); }
    public function apiKeys()           { return $this->hasMany(ApiKey::class); }
    public function tickets()           { return $this->hasMany(SupportTicket::class); }
    public function devices()           { return $this->hasMany(UserDevice::class); }

    public function getFullNameAttribute(): string { return "{$this->first_name} {$this->last_name}"; }
    public function isActive(): bool   { return $this->status === 'active'; }
    public function isAdmin(): bool    { return in_array($this->user_type, ['admin','assistant_admin','customer_support']); }
    public function isApiUser(): bool  { return $this->user_type === 'api_user' && $this->api_access_enabled; }

    public function verifyTransactionPin(string $pin): bool
    {
        return \Hash::check($pin, $this->transaction_pin);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status','user_type','email'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
