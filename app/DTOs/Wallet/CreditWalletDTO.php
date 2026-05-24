<?php
namespace App\DTOs\Wallet;
class CreditWalletDTO {
    public function __construct(
        public readonly int $userId, public readonly float $amount,
        public readonly string $reference, public readonly string $description,
        public readonly string $type = 'credit', public readonly array $meta = [],
    ) {}
    public static function fromArray(array $data): self {
        return new self($data['user_id'],(float)$data['amount'],$data['reference'],
            $data['description'],$data['type'] ?? 'credit',$data['meta'] ?? []);
    }
}
