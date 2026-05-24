<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'ulid'              => Str::ulid(),
            'first_name'        => fake()->firstName(),
            'last_name'         => fake()->lastName(),
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => '0' . fake()->numerify('8#########'),
            'username'          => fake()->unique()->userName(),
            'password'          => Hash::make('password'),
            'transaction_pin'   => null,
            'user_type'         => 'user',
            'referral_code'     => Str::upper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
            'api_access_enabled'=> false,
            'two_factor_enabled'=> false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn() => ['user_type' => 'admin']);
    }

    public function agent(): static
    {
        return $this->state(fn() => ['user_type' => 'agent']);
    }

    public function apiUser(): static
    {
        return $this->state(fn() => ['user_type' => 'api_user', 'api_access_enabled' => true]);
    }

    public function suspended(): static
    {
        return $this->state(fn() => ['status' => 'suspended']);
    }

    public function unverified(): static
    {
        return $this->state(fn() => ['email_verified_at' => null]);
    }
}
