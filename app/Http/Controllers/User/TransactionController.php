<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->when($request->service_type, fn($q) => $q->where('service_type', $request->service_type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('beneficiary', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => TransactionResource::collection($transactions),
            'meta'    => [
                'total'        => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', $request->user()->id)
            ->where(fn($q) => $q->where('ulid', $id)->orWhere('reference', $id)->orWhere('id', $id))
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => new TransactionResource($transaction)]);
    }
}
