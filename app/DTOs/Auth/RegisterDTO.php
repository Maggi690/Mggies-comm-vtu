<?php
namespace App\DTOs\Auth;
class RegisterDTO {
    public function __construct(
        public readonly string $firstName, public readonly string $lastName,
        public readonly string $email, public readonly string $phone,
        public readonly string $password, public readonly ?string $referralCode = null,
        public readonly string $userType = 'user',
    ) {}
    public static function fromArray(array $data): self {
        return new self($data['first_name'],$data['last_name'],$data['email'],
            $data['phone'],$data['password'],$data['referral_code'] ?? null,$data['user_type'] ?? 'user');
    }
}
