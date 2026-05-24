<?php
namespace App\DTOs\Vtu;
class AirtimeDTO {
    public function __construct(
        public readonly int $userId, public readonly string $network,
        public readonly string $phone, public readonly float $amount,
        public readonly string $reference, public readonly ?int $apiKeyId = null,
        public readonly array $meta = [],
    ) {}
    public static function fromArray(array $data, int $userId): self {
        return new self($userId,strtolower($data['network']),$data['phone'],(float)$data['amount'],
            $data['reference'] ?? 'TXN-'.strtoupper(uniqid()),$data['api_key_id'] ?? null,$data['meta'] ?? []);
    }
}
