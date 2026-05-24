<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\DTOs\Wallet\CreditWalletDTO;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly \App\Integrations\Monnify\MonnifyIntegration $monnify,
        private readonly \App\Integrations\Paystack\PaystackIntegration $paystack,
        private readonly \App\Integrations\Flutterwave\FlutterwaveIntegration $flutterwave,
    ) {}

    /**
     * Initialize a payment via preferred gateway.
     */
    public function initialize(User $user, float $amount, string $gateway, string $callbackUrl): array
    {
        $reference = 'PAY-' . Str::upper(Str::random(16));

        return match ($gateway) {
            'monnify'      => $this->monnify->initializePayment($user, $amount, $reference, $callbackUrl),
            'paystack'     => $this->paystack->initializePayment($user, $amount, $reference, $callbackUrl),
            'flutterwave'  => $this->flutterwave->initializePayment($user, $amount, $reference, $callbackUrl),
            default        => throw new PaymentException("Unsupported gateway: {$gateway}"),
        };
    }

    /**
     * Verify payment and credit wallet if successful.
     */
    public function verify(string $reference, string $gateway): array
    {
        $result = match ($gateway) {
            'monnify'      => $this->monnify->verifyPayment($reference),
            'paystack'     => $this->paystack->verifyPayment($reference),
            'flutterwave'  => $this->flutterwave->verifyPayment($reference),
            default        => throw new PaymentException("Unsupported gateway: {$gateway}"),
        };

        if ($result['status'] === 'successful') {
            $this->creditWalletFromPayment($result);
        }

        return $result;
    }

    /**
     * Create a Monnify reserved account for a user.
     */
    public function createReservedAccount(User $user): array
    {
        return $this->monnify->createReservedAccount($user);
    }

    /**
     * Create a virtual account (Paga).
     */
    public function createVirtualAccount(User $user): array
    {
        /** @var \App\Integrations\Paga\PagaIntegration $paga */
        $paga = app(\App\Integrations\Paga\PagaIntegration::class);
        return $paga->createAccount($user);
    }

    /**
     * Handle confirmed payment — credit user wallet.
     */
    public function creditWalletFromPayment(array $paymentData): void
    {
        $userId = $paymentData['user_id'] ?? null;
        $amount = $paymentData['amount'] ?? 0;
        $ref    = $paymentData['reference'] ?? Str::ulid();

        if (!$userId || $amount <= 0) {
            throw new PaymentException("Invalid payment data for wallet credit.");
        }

        // Prevent double-credit
        $alreadyCredited = \App\Models\WalletTransaction::where('reference', 'FUND-' . $ref)->exists();
        if ($alreadyCredited) return;

        $dto = new CreditWalletDTO(
            userId:      $userId,
            amount:      $amount,
            reference:   'FUND-' . $ref,
            description: "Wallet funding via " . ($paymentData['gateway'] ?? 'payment'),
            type:        'funding',
            meta:        $paymentData,
        );

        $this->walletService->credit($dto);
    }
}
