<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Transactions
 * @authenticated
 */
class AdminTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin|assistant_admin|customer_support');
    }

    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::with(['user', 'provider'])
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('beneficiary', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->service_type, fn($q) => $q->where('service_type', $request->service_type))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->provider_id, fn($q) => $q->where('provider_id', $request->provider_id))
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc')
            ->paginate($request->per_page ?? 25);

        $summary = [
            'total_count'   => $transactions->total(),
            'total_amount'  => Transaction::when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
                                ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
                                ->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => TransactionResource::collection($transactions),
            'meta'    => [
                'total'        => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $txn = Transaction::with(['user', 'provider', 'walletTransaction', 'refund'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => new TransactionResource($txn)]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $this->middleware('role:admin|assistant_admin');

        $txn = Transaction::findOrFail($id);

        if ($txn->status === 'refunded') {
            return response()->json(['success' => false, 'message' => 'Transaction already refunded.'], 422);
        }

        if ($txn->status !== 'failed') {
            $request->validate(['reason' => 'required|string']);
        }

        $walletService = app(\App\Services\Wallet\WalletService::class);
        $walletService->refund($txn->user_id, $txn->amount, $txn->reference, $txn->reference);

        $txn->update(['status' => 'refunded', 'refunded_at' => now()]);

        \App\Models\WalletRefund::create([
            'transaction_id' => $txn->id,
            'user_id'        => $txn->user_id,
            'amount'         => $txn->amount,
            'reason'         => $request->reason ?? 'Manual refund',
            'admin_id'       => auth()->id(),
            'status'         => 'completed',
        ]);

        return response()->json(['success' => true, 'message' => "₦{$txn->amount} refunded to user's wallet."]);
    }

    public function retry(int $id): JsonResponse
    {
        $txn = Transaction::findOrFail($id);

        if ($txn->status !== 'failed') {
            return response()->json(['success' => false, 'message' => 'Only failed transactions can be retried.'], 422);
        }

        \App\Jobs\Vtu\RetryTransactionJob::dispatch($txn);

        return response()->json(['success' => true, 'message' => 'Transaction queued for retry.']);
    }

    public function export(Request $request)
    {
        $request->validate(['format' => 'required|in:csv,excel']);

        \App\Jobs\Report\ExportTransactionsJob::dispatch(
            $request->all(),
            auth()->id(),
            $request->format,
        );

        return response()->json(['success' => true, 'message' => 'Export queued. You will receive it via email.']);
    }
}
