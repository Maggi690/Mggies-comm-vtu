<?php

namespace App\Http\Controllers\User;

use App\DTOs\Wallet\CreditWalletDTO;
use App\DTOs\Wallet\DebitWalletDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\WalletResource;
use App\Http\Resources\Wallet\WalletTransactionResource;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Wallet
 * @authenticated
 */
class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * Get wallet balance
     */
    public function balance(Request $request): JsonResponse
    {
        $balance = $this->walletService->getBalance($request->user()->id);
        return response()->json(['success' => true, 'data' => $balance]);
    }

    /**
     * Credit wallet (admin/system use or payment webhook callback)
     *
     * @bodyParam amount number required Amount to credit. Example: 5000
     * @bodyParam reference string required Unique payment reference.
     * @bodyParam description string required Transaction description.
     */
    public function credit(Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|numeric|min:1',
            'reference'   => 'required|string|unique:wallet_transactions,reference',
            'description' => 'required|string',
        ]);

        $dto = new CreditWalletDTO(
            userId:      $request->user()->id,
            amount:      $request->amount,
            reference:   $request->reference,
            description: $request->description,
            meta:        $request->only(['gateway', 'payment_reference']),
        );

        $txn = $this->walletService->credit($dto);

        return response()->json([
            'success' => true,
            'message' => "₦{$request->amount} credited to your wallet.",
            'data'    => new WalletTransactionResource($txn),
        ]);
    }

    /**
     * Debit wallet
     *
     * @bodyParam amount number required Amount to debit. Example: 1000
     * @bodyParam reference string required Unique reference.
     * @bodyParam description string required Transaction description.
     * @bodyParam pin string required 4-digit transaction PIN.
     */
    public function debit(Request $request): JsonResponse
    {
        $request->validate([
            'amount'      => 'required|numeric|min:1',
            'reference'   => 'required|string',
            'description' => 'required|string',
            'pin'         => 'required|string|digits:4',
        ]);

        $dto = new DebitWalletDTO(
            userId:      $request->user()->id,
            amount:      $request->amount,
            reference:   $request->reference,
            description: $request->description,
            pin:         $request->pin,
        );

        $txn = $this->walletService->debit($dto);

        return response()->json([
            'success' => true,
            'message' => "₦{$request->amount} debited from your wallet.",
            'data'    => new WalletTransactionResource($txn),
        ]);
    }

    /**
     * Freeze wallet funds
     *
     * @bodyParam amount number required Amount to freeze.
     * @bodyParam reason string required Reason for freeze.
     * @bodyParam reference string required Unique reference.
     */
    public function freeze(Request $request): JsonResponse
    {
        $request->validate([
            'amount'    => 'required|numeric|min:1',
            'reason'    => 'required|string',
            'reference' => 'required|string',
        ]);

        $hold = $this->walletService->freeze(
            $request->user()->id,
            $request->amount,
            $request->reason,
            $request->reference,
        );

        return response()->json([
            'success' => true,
            'message' => "₦{$request->amount} has been frozen in your wallet.",
            'data'    => $hold,
        ]);
    }

    /**
     * Release frozen funds
     *
     * @urlParam holdId integer required The hold ID. Example: 1
     */
    public function release(Request $request, int $holdId): JsonResponse
    {
        $this->walletService->release($holdId);
        return response()->json(['success' => true, 'message' => 'Funds released successfully.']);
    }

    /**
     * Wallet transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $txns = $user->wallet->transactions()
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => WalletTransactionResource::collection($txns),
            'meta'    => [
                'current_page' => $txns->currentPage(),
                'per_page'     => $txns->perPage(),
                'total'        => $txns->total(),
                'last_page'    => $txns->lastPage(),
            ],
        ]);
    }
}
