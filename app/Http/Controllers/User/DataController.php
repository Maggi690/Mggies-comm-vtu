<?php

namespace App\Http\Controllers\User;

use App\DTOs\Vtu\DataDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\TransactionResource;
use App\Services\Vtu\DataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Data
 * @authenticated
 */
class DataController extends Controller
{
    public function __construct(private readonly DataService $dataService) {}

    /**
     * Get available data plans
     *
     * @queryParam network string Filter by network (mtn, airtel, glo, 9mobile). Example: mtn
     * @queryParam plan_type string Filter by plan type (sme, sme2, cg, cg2). Example: sme
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = $this->dataService->getPlans(
            $request->query('network'),
            $request->query('plan_type'),
        );

        return response()->json([
            'success' => true,
            'data'    => $plans->groupBy('network'),
        ]);
    }

    /**
     * Purchase data bundle
     *
     * @bodyParam network string required Network provider. Example: mtn
     * @bodyParam phone string required Recipient phone number. Example: 08012345678
     * @bodyParam plan_id integer required Data plan ID from /api/data/plans. Example: 5
     * @bodyParam pin string required 4-digit transaction PIN. Example: 1234
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'network' => 'required|in:mtn,airtel,glo,9mobile',
            'phone'   => ['required', 'string', 'regex:/^(0|\+234)[789][01]\d{8}$/'],
            'plan_id' => 'required|integer|exists:data_plans,id',
            'pin'     => 'required|digits:4',
        ]);

        if (!$request->user()->verifyTransactionPin($request->pin)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction PIN.'], 422);
        }

        $plan = \App\Models\DataPlan::findOrFail($request->plan_id);

        $dto = DataDTO::fromArray(array_merge($request->validated(), [
            'amount'    => $plan->selling_price,
            'reference' => 'DAT-' . strtoupper(uniqid()),
        ]), $request->user()->id);

        \App\Jobs\Vtu\ProcessDataJob::dispatch($dto);

        return response()->json([
            'success' => true,
            'message' => 'Data purchase queued successfully.',
            'data'    => [
                'reference' => $dto->reference,
                'network'   => $dto->network,
                'phone'     => $dto->phone,
                'plan'      => $plan->name,
                'amount'    => $plan->selling_price,
                'status'    => 'pending',
            ],
        ], 202);
    }
}
