<?php
namespace App\DTOs\Wallet;
class DebitWalletDTO {
    public function __construct(
        public readonly int $userId, public readonly float $amount,
        public readonly string $reference, public readonly string $description,
        public readonly string $pin, public readonly array $meta = [],
    ) {}
    public static function fromArray(array $data): self {
        return new self($data['user_id'],(float)$data['amount'],$data['reference'],
            $data['description'],$data['pin'],$data['meta'] ?? []);
    }
}
