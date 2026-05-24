<?php

namespace App\Http\Controllers\User;

use App\DTOs\Vtu\AirtimeDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\TransactionResource;
use App\Services\Vtu\AirtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Airtime
 * @authenticated
 */
class AirtimeController extends Controller
{
    public function __construct(private readonly AirtimeService $airtimeService) {}

    /**
     * Purchase airtime
     *
     * @bodyParam network string required Network provider (mtn, airtel, glo, 9mobile). Example: mtn
     * @bodyParam phone string required Recipient phone number. Example: 08012345678
     * @bodyParam amount number required Amount to topup. Example: 500
     * @bodyParam pin string required 4-digit transaction PIN. Example: 1234
     * @response 200 {"success":true,"message":"Airtime purchase successful","data":{}}
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'network' => 'required|in:mtn,airtel,glo,9mobile',
            'phone'   => ['required', 'string', 'regex:/^(0|\+234)[789][01]\d{8}$/'],
            'amount'  => 'required|numeric|min:50|max:50000',
            'pin'     => 'required|digits:4',
        ]);

        // Verify PIN before queueing
        if (!$request->user()->verifyTransactionPin($request->pin)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction PIN.'], 422);
        }

        $dto = AirtimeDTO::fromArray(array_merge($request->validated(), [
            'reference' => 'AIR-' . strtoupper(uniqid()),
        ]), $request->user()->id);

        // Dispatch to queue
        \App\Jobs\Vtu\ProcessAirtimeJob::dispatch($dto);

        return response()->json([
            'success' => true,
            'message' => 'Airtime purchase queued successfully.',
            'data'    => [
                'reference' => $dto->reference,
                'network'   => $dto->network,
                'phone'     => $dto->phone,
                'amount'    => $dto->amount,
                'status'    => 'pending',
            ],
        ], 202);
    }

    /**
     * Get airtime transaction status
     *
     * @urlParam id string required Transaction ULID or reference. Example: 01HXYZ...
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $transaction = $this->airtimeService->getStatus($id);

        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }
}
