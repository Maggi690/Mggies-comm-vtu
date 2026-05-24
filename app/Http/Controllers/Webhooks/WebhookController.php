<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Integrations\Monnify\MonnifyIntegration;
use App\Integrations\Paystack\PaystackIntegration;
use App\Integrations\Flutterwave\FlutterwaveIntegration;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook handlers for payment gateways — NOT authenticated with Sanctum.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly MonnifyIntegration $monnify,
        private readonly PaystackIntegration $paystack,
        private readonly FlutterwaveIntegration $flutterwave,
    ) {}

    // ─── Monnify ────────────────────────────────────────────────────────────────

    public function monnify(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('monnify-signature') ?? '';

        if (!$this->monnify->validateWebhook($payload, $signature)) {
            Log::warning('Invalid Monnify webhook signature');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $this->logWebhook('monnify', $request->all());

        $event = $request->input('eventType');

        return match ($event) {
            'SUCCESSFUL_TRANSACTION'    => $this->handleMonnifySuccess($request->all()),
            'REVERSED_TRANSACTION'      => $this->handleMonnifyReversal($request->all()),
            'SUCCESSFUL_DISBURSEMENT'   => response()->json(['status' => 'ok']),
            default                     => response()->json(['status' => 'ignored']),
        };
    }

    private function handleMonnifySuccess(array $data): JsonResponse
    {
        try {
            $txn = $data['eventData'] ?? [];

            // Find user by payment reference
            $reference = $txn['paymentReference'] ?? null;
            $user      = $this->findUserByReference($reference);

            if (!$user) {
                Log::error('Monnify webhook: user not found for reference', ['ref' => $reference]);
                return response()->json(['status' => 'user_not_found'], 200);
            }

            $this->paymentService->creditWalletFromPayment([
                'user_id'   => $user->id,
                'amount'    => $txn['amountPaid'],
                'reference' => $reference,
                'gateway'   => 'monnify',
                'raw'       => $txn,
            ]);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Monnify webhook handler error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 200); // Always 200 to prevent retries
        }
    }

    private function handleMonnifyReversal(array $data): JsonResponse
    {
        Log::info('Monnify reversal webhook received', $data);
        // TODO: Implement reversal logic
        return response()->json(['status' => 'ok']);
    }

    // ─── Paystack ───────────────────────────────────────────────────────────────

    public function paystack(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('x-paystack-signature') ?? '';

        if (!$this->paystack->validateWebhook($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $this->logWebhook('paystack', $request->all());

        $event = $request->input('event');

        try {
            if ($event === 'charge.success') {
                $txn = $request->input('data', []);
                $this->paymentService->creditWalletFromPayment([
                    'user_id'   => $txn['metadata']['user_id'] ?? null,
                    'amount'    => $txn['amount'] / 100,
                    'reference' => $txn['reference'],
                    'gateway'   => 'paystack',
                    'raw'       => $txn,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Paystack webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }

    // ─── Flutterwave ────────────────────────────────────────────────────────────

    public function flutterwave(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('verif-hash') ?? '';

        if ($signature !== config('services.flutterwave.webhook_secret')) {
            Log::warning('Invalid Flutterwave webhook hash');
            return response()->json(['status' => 'invalid hash'], 401);
        }

        $this->logWebhook('flutterwave', $request->all());

        $event = $request->input('event');

        try {
            if ($event === 'charge.completed' && $request->input('data.status') === 'successful') {
                $txn = $request->input('data', []);
                $this->paymentService->creditWalletFromPayment([
                    'user_id'   => $txn['meta']['user_id'] ?? null,
                    'amount'    => $txn['amount'],
                    'reference' => $txn['tx_ref'],
                    'gateway'   => 'flutterwave',
                    'raw'       => $txn,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Flutterwave webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }

    // ─── Developer Webhooks ─────────────────────────────────────────────────────

    /**
     * Forward webhook to developer's registered URL
     */
    public function developer(Request $request, string $apiKeyPublic): JsonResponse
    {
        $apiKey = \App\Models\ApiKey::where('public_key', $apiKeyPublic)
            ->where('status', 'active')
            ->first();

        if (!$apiKey || !$apiKey->webhook_url) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $this->logWebhook('developer', $request->all(), $apiKey->id);

        // Queue webhook delivery
        \App\Jobs\Webhook\DeliverWebhookJob::dispatch($apiKey->webhook_url, $request->all(), $apiKey->webhook_secret);

        return response()->json(['status' => 'ok']);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function logWebhook(string $gateway, array $payload, ?int $apiKeyId = null): void
    {
        \App\Models\WebhookLog::create([
            'gateway'    => $gateway,
            'payload'    => $payload,
            'api_key_id' => $apiKeyId,
            'ip_address' => request()->ip(),
            'status'     => 'received',
        ]);
    }

    private function findUserByReference(string $reference): ?\App\Models\User
    {
        // Reference format: PAY-{USER_ID}-{RANDOM}
        // Or look up via wallet transaction table
        $txn = \App\Models\WalletTransaction::where('reference', 'FUND-' . $reference)->first();
        if ($txn) return $txn->wallet->user;

        // Try pending transaction
        preg_match('/PAY-(\d+)-/', $reference, $matches);
        if (!empty($matches[1])) {
            return \App\Models\User::find($matches[1]);
        }

        return null;
    }
}
