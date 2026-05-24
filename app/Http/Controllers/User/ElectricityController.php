<?php

namespace App\Http\Controllers\User;

use App\DTOs\Vtu\ElectricityDTO;
use App\Http\Controllers\Controller;
use App\Services\Vtu\ElectricityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Electricity
 * @authenticated
 */
class ElectricityController extends Controller
{
    public function __construct(private readonly ElectricityService $electricityService) {}

    /**
     * Validate meter number
     *
     * @bodyParam disco string required Distribution company (ekedc, ikedc, aedc, phed, etc). Example: ekedc
     * @bodyParam meter_number string required Meter number. Example: 1234567890
     * @bodyParam meter_type string required Meter type (prepaid or postpaid). Example: prepaid
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'disco'        => 'required|string',
            'meter_number' => 'required|string|min:6|max:20',
            'meter_type'   => 'required|in:prepaid,postpaid',
        ]);

        $result = $this->electricityService->validateMeter(
            $request->disco,
            $request->meter_number,
            $request->meter_type,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Purchase electricity token
     *
     * @bodyParam disco string required Distribution company code. Example: ekedc
     * @bodyParam meter_number string required Meter number. Example: 1234567890
     * @bodyParam meter_type string required prepaid or postpaid. Example: prepaid
     * @bodyParam amount number required Amount (min 100). Example: 5000
     * @bodyParam phone string optional Contact phone number.
     * @bodyParam pin string required 4-digit transaction PIN. Example: 1234
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'disco'        => 'required|string',
            'meter_number' => 'required|string',
            'meter_type'   => 'required|in:prepaid,postpaid',
            'amount'       => 'required|numeric|min:100',
            'phone'        => 'nullable|string',
            'pin'          => 'required|digits:4',
        ]);

        if (!$request->user()->verifyTransactionPin($request->pin)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction PIN.'], 422);
        }

        $dto = ElectricityDTO::fromArray(array_merge($request->validated(), [
            'reference' => 'ELC-' . strtoupper(uniqid()),
        ]), $request->user()->id);

        \App\Jobs\Vtu\ProcessElectricityJob::dispatch($dto);

        return response()->json([
            'success' => true,
            'message' => 'Electricity purchase queued.',
            'data'    => [
                'reference'    => $dto->reference,
                'disco'        => $dto->disco,
                'meter_number' => $dto->meterNumber,
                'amount'       => $dto->amount,
                'status'       => 'pending',
            ],
        ], 202);
    }
}
