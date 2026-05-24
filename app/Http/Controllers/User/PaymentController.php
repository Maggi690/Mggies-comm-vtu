<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Payments
 * @authenticated
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Initialize a wallet funding payment
     *
     * @bodyParam amount number required Amount to fund (min 100). Example: 5000
     * @bodyParam gateway string required Payment gateway (monnify, paystack, flutterwave). Example: paystack
     * @bodyParam callback_url string required Redirect URL after payment. Example: https://app.example.com/callback
     */
    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'amount'       => 'required|numeric|min:100|max:5000000',
            'gateway'      => 'required|in:monnify,paystack,flutterwave',
            'callback_url' => 'required|url',
        ]);

        $result = $this->paymentService->initialize(
            $request->user(),
            $request->amount,
            $request->gateway,
            $request->callback_url,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Verify a payment
     *
     * @bodyParam reference string required Payment reference. Example: PAY-ABCDEF123456
     * @bodyParam gateway string required Gateway used. Example: paystack
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
            'gateway'   => 'required|in:monnify,paystack,flutterwave',
        ]);

        $result = $this->paymentService->verify($request->reference, $request->gateway);

        return response()->json([
            'success' => true,
            'message' => $result['status'] === 'successful' ? 'Payment verified and wallet credited.' : 'Payment not successful.',
            'data'    => $result,
        ]);
    }

    /**
     * Create a reserved bank account for automatic deposits
     */
    public function createReservedAccount(Request $request): JsonResponse
    {
        $result = $this->paymentService->createReservedAccount($request->user());
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Create a virtual account (Paga)
     */
    public function createVirtualAccount(Request $request): JsonResponse
    {
        $result = $this->paymentService->createVirtualAccount($request->user());
        return response()->json(['success' => true, 'data' => $result]);
    }
}
