<?php

namespace App\Services;

use App\DTOs\Auth\RegisterDTO;
use App\Exceptions\AuthException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(private readonly WalletService $walletService) {}

    public function register(RegisterDTO $dto): array
    {
        // Check blacklists
        if (\App\Models\BlacklistedEmail::where('email', $dto->email)->exists()) {
            throw new AuthException('This email address is not allowed to register.');
        }

        return DB::transaction(function () use ($dto) {
            // Resolve referral
            $referredBy = null;
            if ($dto->referralCode) {
                $referrer = User::where('referral_code', $dto->referralCode)->first();
                if ($referrer) {
                    $referredBy = $referrer->id;
                }
            }

            $user = User::create([
                'ulid'          => Str::ulid(),
                'first_name'    => $dto->firstName,
                'last_name'     => $dto->lastName,
                'email'         => $dto->email,
                'phone'         => $dto->phone,
                'username'      => $this->generateUsername($dto->firstName, $dto->lastName),
                'password'      => Hash::make($dto->password),
                'user_type'     => $dto->userType,
                'referral_code' => Str::upper(Str::random(8)),
                'referred_by'   => $referredBy,
                'status'        => 'active',
            ]);

            // Assign default role
            $user->assignRole($dto->userType);

            // Create wallet
            $this->walletService->createWallet($user->id);

            // Track referral
            if ($referredBy) {
                \App\Models\Referral::create([
                    'referrer_id' => $referredBy,
                    'referee_id'  => $user->id,
                    'status'      => 'pending',
                ]);
            }

            // Send verification email
            $user->sendEmailVerificationNotification();

            $token = $user->createToken('auth_token')->plainTextToken;

            return ['user' => $user, 'token' => $token];
        });
    }

    public function login(string $email, string $password, string $ip, string $userAgent): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new AuthException('Invalid credentials.');
        }

        if (!$user->isActive()) {
            throw new AuthException('Your account has been suspended. Contact support.');
        }

        if (\App\Models\BlacklistedEmail::where('email', $email)->exists()) {
            throw new AuthException('Account access denied.');
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        // Track device
        \App\Models\UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'ip_address' => $ip],
            ['user_agent' => $userAgent, 'last_seen_at' => now()]
        );

        // Revoke old tokens (optional — single session)
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user->load('wallet'), 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function setTransactionPin(User $user, string $pin, string $confirmPin): void
    {
        if ($pin !== $confirmPin) {
            throw new AuthException('PIN confirmation does not match.');
        }
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            throw new AuthException('PIN must be exactly 4 digits.');
        }

        $user->update(['transaction_pin' => Hash::make($pin)]);
    }

    public function resetTransactionPin(User $user, string $currentPassword, string $newPin): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new AuthException('Current password is incorrect.');
        }

        $user->update(['transaction_pin' => Hash::make($newPin)]);
    }

    private function generateUsername(string $firstName, string $lastName): string
    {
        $base     = strtolower($firstName . $lastName);
        $username = preg_replace('/[^a-z0-9]/', '', $base);
        $counter  = 0;

        while (User::where('username', $username . ($counter ?: ''))->exists()) {
            $counter++;
        }

        return $username . ($counter ?: '');
    }
}
