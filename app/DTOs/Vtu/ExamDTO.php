<?php
namespace App\DTOs\Vtu;
class ExamDTO {
    public function __construct(
        public readonly int $userId, public readonly string $examType,
        public readonly int $quantity, public readonly float $amount,
        public readonly string $reference, public readonly ?int $apiKeyId = null,
    ) {}
    public static function fromArray(array $data, int $userId): self {
        return new self($userId,strtolower($data['exam_type']),(int)($data['quantity'] ?? 1),
            (float)$data['amount'],$data['reference'] ?? 'TXN-'.strtoupper(uniqid()),$data['api_key_id'] ?? null);
    }
}
