<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Vtu\AirtimeDTO;
use App\DTOs\Vtu\DataDTO;
use App\DTOs\Vtu\CableDTO;
use App\DTOs\Vtu\ElectricityDTO;
use App\DTOs\Vtu\ExamDTO;
use App\Http\Controllers\Controller;
use App\Services\Vtu\AirtimeService;
use App\Services\Vtu\DataService;
use App\Services\Vtu\CableService;
use App\Services\Vtu\ElectricityService;
use App\Services\Vtu\ExamService;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Developer API v1
 *
 * Third-party developer endpoints. Authenticate with X-API-Key and X-API-Secret headers.
 */
class ApiV1Controller extends Controller
{
    public function __construct(
        private readonly AirtimeService $airtimeService,
        private readonly DataService $dataService,
        private readonly CableService $cableService,
        private readonly ElectricityService $electricityService,
        private readonly ExamService $examService,
    ) {
        $this->middleware('api.key');
    }

    /**
     * Purchase airtime via API
     *
     * @header X-API-Key required Your public API key.
     * @header X-API-Secret required Your secret API key.
     * @bodyParam network string required Network (mtn, airtel, glo, 9mobile). Example: mtn
     * @bodyParam phone string required Recipient phone. Example: 08012345678
     * @bodyParam amount number required Amount in NGN. Example: 500
     * @bodyParam reference string optional Your unique transaction reference.
     */
    public function airtime(Request $request): JsonResponse
    {
        $request->validate([
            'network'   => 'required|in:mtn,airtel,glo,9mobile',
            'phone'     => ['required', 'string', 'regex:/^(0|\+234)[789][01]\d{8}$/'],
            'amount'    => 'required|numeric|min:50|max:50000',
            'reference' => 'nullable|string|unique:transactions,reference',
        ]);

        $apiKey = $request->attributes->get('api_key');
        $dto    = AirtimeDTO::fromArray(array_merge($request->validated(), [
            'reference'  => $request->reference ?? 'APIV1-AIR-' . strtoupper(uniqid()),
            'api_key_id' => $apiKey->id,
        ]), $apiKey->user_id);

        $transaction = $this->airtimeService->purchase($dto);

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * Purchase data bundle via API
     *
     * @header X-API-Key required
     * @header X-API-Secret required
     * @bodyParam network string required Example: mtn
     * @bodyParam phone string required Example: 08012345678
     * @bodyParam plan_id integer required Data plan ID.
     * @bodyParam reference string optional Unique reference.
     */
    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'network'   => 'required|in:mtn,airtel,glo,9mobile',
            'phone'     => ['required', 'string', 'regex:/^(0|\+234)[789][01]\d{8}$/'],
            'plan_id'   => 'required|integer|exists:data_plans,id',
            'reference' => 'nullable|string|unique:transactions,reference',
        ]);

        $apiKey = $request->attributes->get('api_key');
        $plan   = \App\Models\DataPlan::findOrFail($request->plan_id);

        $dto = DataDTO::fromArray(array_merge($request->validated(), [
            'amount'     => $plan->selling_price,
            'reference'  => $request->reference ?? 'APIV1-DAT-' . strtoupper(uniqid()),
            'api_key_id' => $apiKey->id,
        ]), $apiKey->user_id);

        $transaction = $this->dataService->purchase($dto);

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * Subscribe cable TV via API
     *
     * @header X-API-Key required
     * @header X-API-Secret required
     */
    public function cable(Request $request): JsonResponse
    {
        $request->validate([
            'provider'         => 'required|in:dstv,gotv,startimes',
            'smartcard_number' => 'required|string',
            'package_code'     => 'required|string',
            'amount'           => 'required|numeric|min:100',
            'phone'            => 'nullable|string',
            'reference'        => 'nullable|string|unique:transactions,reference',
        ]);

        $apiKey = $request->attributes->get('api_key');
        $dto    = CableDTO::fromArray(array_merge($request->validated(), [
            'reference'  => $request->reference ?? 'APIV1-CAB-' . strtoupper(uniqid()),
            'api_key_id' => $apiKey->id,
        ]), $apiKey->user_id);

        $transaction = $this->cableService->purchase($dto);

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * Purchase electricity via API
     *
     * @header X-API-Key required
     * @header X-API-Secret required
     */
    public function electricity(Request $request): JsonResponse
    {
        $request->validate([
            'disco'        => 'required|string',
            'meter_number' => 'required|string',
            'meter_type'   => 'required|in:prepaid,postpaid',
            'amount'       => 'required|numeric|min:100',
            'phone'        => 'nullable|string',
            'reference'    => 'nullable|string|unique:transactions,reference',
        ]);

        $apiKey = $request->attributes->get('api_key');
        $dto    = ElectricityDTO::fromArray(array_merge($request->validated(), [
            'reference'  => $request->reference ?? 'APIV1-ELC-' . strtoupper(uniqid()),
            'api_key_id' => $apiKey->id,
        ]), $apiKey->user_id);

        $transaction = $this->electricityService->purchase($dto);

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * Purchase exam pin via API
     *
     * @header X-API-Key required
     * @header X-API-Secret required
     */
    public function exam(Request $request): JsonResponse
    {
        $request->validate([
            'exam_type' => 'required|in:waec,neco,nabteb,jamb',
            'quantity'  => 'required|integer|min:1|max:5',
            'reference' => 'nullable|string|unique:transactions,reference',
        ]);

        $apiKey = $request->attributes->get('api_key');
        $prices = config('vtu.exam_prices', ['waec' => 4000, 'neco' => 1500, 'nabteb' => 900, 'jamb' => 3500]);
        $amount = ($prices[$request->exam_type] ?? 0) * $request->quantity;

        $dto = ExamDTO::fromArray([
            'exam_type'  => $request->exam_type,
            'quantity'   => $request->quantity,
            'amount'     => $amount,
            'reference'  => $request->reference ?? 'APIV1-EXM-' . strtoupper(uniqid()),
            'api_key_id' => $apiKey->id,
        ], $apiKey->user_id);

        $transaction = $this->examService->purchase($dto);

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * Query transaction status
     *
     * @urlParam reference string required Transaction reference. Example: APIV1-AIR-ABC123
     */
    public function transaction(Request $request, string $reference): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $txn    = \App\Models\Transaction::where('reference', $reference)
            ->where('api_key_id', $apiKey->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($txn),
        ]);
    }

    /**
     * Get available data plans
     */
    public function dataPlans(Request $request): JsonResponse
    {
        $plans = $this->dataService->getPlans(
            $request->query('network'),
            $request->query('plan_type'),
        );

        return response()->json(['success' => true, 'data' => $plans]);
    }

    /**
     * Get wallet balance for API user
     */
    public function balance(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $service = app(\App\Services\Wallet\WalletService::class);
        $balance = $service->getBalance($apiKey->user_id);

        return response()->json(['success' => true, 'data' => $balance]);
    }
}
