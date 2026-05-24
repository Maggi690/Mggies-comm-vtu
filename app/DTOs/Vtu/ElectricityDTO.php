<?php
namespace App\DTOs\Vtu;
class ElectricityDTO {
    public function __construct(
        public readonly int $userId, public readonly string $disco,
        public readonly string $meterNumber, public readonly string $meterType,
        public readonly float $amount, public readonly string $reference,
        public readonly ?string $phone = null, public readonly ?int $apiKeyId = null,
    ) {}
    public static function fromArray(array $data, int $userId): self {
        return new self($userId,strtolower($data['disco']),$data['meter_number'],$data['meter_type'] ?? 'prepaid',
            (float)$data['amount'],$data['reference'] ?? 'TXN-'.strtoupper(uniqid()),$data['phone'] ?? null,$data['api_key_id'] ?? null);
    }
}
