<?php
namespace App\DTOs\Vtu;
class CableDTO {
    public function __construct(
        public readonly int $userId, public readonly string $provider,
        public readonly string $smartcardNumber, public readonly string $packageCode,
        public readonly float $amount, public readonly string $reference,
        public readonly ?string $phone = null, public readonly ?int $apiKeyId = null,
    ) {}
    public static function fromArray(array $data, int $userId): self {
        return new self($userId,strtolower($data['provider']),$data['smartcard_number'],$data['package_code'],
            (float)$data['amount'],$data['reference'] ?? 'TXN-'.strtoupper(uniqid()),$data['phone'] ?? null,$data['api_key_id'] ?? null);
    }
}
