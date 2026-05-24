<?php

namespace App\Services\Wallet;

use App\DTOs\Wallet\CreditWalletDTO;
use App\DTOs\Wallet\DebitWalletDTO;
use App\Events\Wallet\WalletCredited;
use App\Events\Wallet\WalletDebited;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Credit a user's wallet atomically.
     */
    public function credit(CreditWalletDTO $dto): WalletTransaction
    {
        return DB::transaction(function () use ($dto) {
            $wallet = Wallet::where('user_id', $dto->userId)->lockForUpdate()->firstOrFail();

            $balanceBefore       = $wallet->balance;
            $ledgerBefore        = $wallet->ledger_balance;
            $wallet->balance     += $dto->amount;
            $wallet->ledger_balance += $dto->amount;
            $wallet->last_transaction_at = now();
            $wallet->save();

            $txn = WalletTransaction::create([
                'wallet_id'             => $wallet->id,
                'ulid'                  => Str::ulid(),
                'type'                  => $dto->type,
                'amount'                => $dto->amount,
                'balance_before'        => $balanceBefore,
                'balance_after'         => $wallet->balance,
                'ledger_balance_before' => $ledgerBefore,
                'ledger_balance_after'  => $wallet->ledger_balance,
                'reference'             => $dto->reference,
                'description'           => $dto->description,
                'meta'                  => $dto->meta,
                'status'                => 'successful',
            ]);

            event(new WalletCredited($wallet, $txn));

            Log::info('Wallet credited', [
                'user_id'  => $dto->userId,
                'amount'   => $dto->amount,
                'ref'      => $dto->reference,
            ]);

            return $txn;
        });
    }

    /**
     * Debit a user's wallet atomically with balance check.
     */
    public function debit(DebitWalletDTO $dto): WalletTransaction
    {
        return DB::transaction(function () use ($dto) {
            $user   = User::findOrFail($dto->userId);
            $wallet = Wallet::where('user_id', $dto->userId)->lockForUpdate()->firstOrFail();

            // Verify transaction PIN
            if (!$user->verifyTransactionPin($dto->pin)) {
                throw new \App\Exceptions\InvalidPinException('Invalid transaction PIN.');
            }

            if (!$wallet->hasSufficientBalance($dto->amount)) {
                throw new \App\Exceptions\InsufficientBalanceException(
                    "Insufficient balance. Available: ₦{$wallet->available_balance}"
                );
            }

            $balanceBefore       = $wallet->balance;
            $ledgerBefore        = $wallet->ledger_balance;
            $wallet->balance     -= $dto->amount;
            $wallet->ledger_balance -= $dto->amount;
            $wallet->last_transaction_at = now();
            $wallet->save();

            $txn = WalletTransaction::create([
                'wallet_id'             => $wallet->id,
                'ulid'                  => Str::ulid(),
                'type'                  => 'debit',
                'amount'                => $dto->amount,
                'balance_before'        => $balanceBefore,
                'balance_after'         => $wallet->balance,
                'ledger_balance_before' => $ledgerBefore,
                'ledger_balance_after'  => $wallet->ledger_balance,
                'reference'             => $dto->reference,
                'description'           => $dto->description,
                'meta'                  => $dto->meta,
                'status'                => 'successful',
            ]);

            event(new WalletDebited($wallet, $txn));

            return $txn;
        });
    }

    /**
     * Debit wallet without PIN for internal service use (e.g., VTU purchase).
     */
    public function debitInternal(int $userId, float $amount, string $reference, string $description, array $meta = []): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $reference, $description, $meta) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if (!$wallet->hasSufficientBalance($amount)) {
                throw new \App\Exceptions\InsufficientBalanceException(
                    "Insufficient balance. Available: ₦{$wallet->available_balance}"
                );
            }

            $balanceBefore       = $wallet->balance;
            $ledgerBefore        = $wallet->ledger_balance;
            $wallet->balance     -= $amount;
            $wallet->ledger_balance -= $amount;
            $wallet->last_transaction_at = now();
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id'             => $wallet->id,
                'ulid'                  => Str::ulid(),
                'type'                  => 'debit',
                'amount'                => $amount,
                'balance_before'        => $balanceBefore,
                'balance_after'         => $wallet->balance,
                'ledger_balance_before' => $ledgerBefore,
                'ledger_balance_after'  => $wallet->ledger_balance,
                'reference'             => $reference,
                'description'           => $description,
                'meta'                  => $meta,
                'status'                => 'successful',
            ]);
        });
    }

    /**
     * Freeze (hold) funds in wallet.
     */
    public function freeze(int $userId, float $amount, string $reason, string $reference): WalletHold
    {
        return DB::transaction(function () use ($userId, $amount, $reason, $reference) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if (!$wallet->hasSufficientBalance($amount)) {
                throw new \App\Exceptions\InsufficientBalanceException('Insufficient balance to freeze.');
            }

            $wallet->frozen_balance += $amount;
            $wallet->save();

            return WalletHold::create([
                'wallet_id' => $wallet->id,
                'amount'    => $amount,
                'reason'    => $reason,
                'reference' => $reference,
                'status'    => 'active',
            ]);
        });
    }

    /**
     * Release frozen funds.
     */
    public function release(int $holdId): void
    {
        DB::transaction(function () use ($holdId) {
            $hold   = WalletHold::findOrFail($holdId);
            $wallet = Wallet::where('id', $hold->wallet_id)->lockForUpdate()->firstOrFail();

            $wallet->frozen_balance = max(0, $wallet->frozen_balance - $hold->amount);
            $wallet->save();

            $hold->update(['status' => 'released', 'released_at' => now()]);
        });
    }

    /**
     * Refund a failed transaction back to wallet.
     */
    public function refund(int $userId, float $amount, string $reference, string $originalReference): WalletTransaction
    {
        $dto = new CreditWalletDTO(
            userId:      $userId,
            amount:      $amount,
            reference:   'REFUND-' . $reference,
            description: "Refund for transaction #{$originalReference}",
            type:        'refund',
            meta:        ['original_reference' => $originalReference],
        );

        return $this->credit($dto);
    }

    /**
     * Get wallet balance for a user.
     */
    public function getBalance(int $userId): array
    {
        $wallet = Wallet::where('user_id', $userId)->firstOrFail();
        return [
            'balance'           => $wallet->balance,
            'available_balance' => $wallet->available_balance,
            'ledger_balance'    => $wallet->ledger_balance,
            'frozen_balance'    => $wallet->frozen_balance,
            'currency'          => $wallet->currency,
        ];
    }

    /**
     * Create a new wallet for a user.
     */
    public function createWallet(int $userId): Wallet
    {
        return Wallet::create([
            'user_id'        => $userId,
            'ulid'           => Str::ulid(),
            'balance'        => 0,
            'ledger_balance' => 0,
            'frozen_balance' => 0,
            'currency'       => 'NGN',
            'status'         => 'active',
        ]);
    }
}
