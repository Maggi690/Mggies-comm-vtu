<?php

namespace App\Http\Controllers\User;

use App\DTOs\Vtu\CableDTO;
use App\Http\Controllers\Controller;
use App\Services\Vtu\CableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Cable TV
 * @authenticated
 */
class CableController extends Controller
{
    public function __construct(private readonly CableService $cableService) {}

    /**
     * Validate smartcard number
     *
     * @bodyParam provider string required Cable provider (dstv, gotv, startimes). Example: dstv
     * @bodyParam smartcard_number string required Smartcard/IUC number. Example: 1234567890
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'provider'         => 'required|in:dstv,gotv,startimes',
            'smartcard_number' => 'required|string|min:5|max:20',
        ]);

        $result = $this->cableService->validateSmartcard(
            $request->provider,
            $request->smartcard_number,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Subscribe/renew cable TV
     *
     * @bodyParam provider string required Cable provider. Example: dstv
     * @bodyParam smartcard_number string required Smartcard number. Example: 1234567890
     * @bodyParam package_code string required Subscription package code. Example: DSTV5
     * @bodyParam amount number required Subscription amount. Example: 24500
     * @bodyParam phone string optional Contact phone number. Example: 08012345678
     * @bodyParam pin string required 4-digit transaction PIN. Example: 1234
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'provider'         => 'required|in:dstv,gotv,startimes',
            'smartcard_number' => 'required|string',
            'package_code'     => 'required|string',
            'amount'           => 'required|numeric|min:100',
            'phone'            => 'nullable|string',
            'pin'              => 'required|digits:4',
        ]);

        if (!$request->user()->verifyTransactionPin($request->pin)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction PIN.'], 422);
        }

        $dto = CableDTO::fromArray(array_merge($request->validated(), [
            'reference' => 'CAB-' . strtoupper(uniqid()),
        ]), $request->user()->id);

        \App\Jobs\Vtu\ProcessCableJob::dispatch($dto);

        return response()->json([
            'success' => true,
            'message' => 'Cable TV subscription queued.',
            'data'    => [
                'reference'        => $dto->reference,
                'provider'         => $dto->provider,
                'smartcard_number' => $dto->smartcardNumber,
                'amount'           => $dto->amount,
                'status'           => 'pending',
            ],
        ], 202);
    }
}
