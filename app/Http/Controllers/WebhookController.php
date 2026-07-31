<?php

namespace App\Http\Controllers;

use App\Services\DepositService;
use App\Services\Gateways\FlutterwaveGateway;
use App\Services\Gateways\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server payment notifications.
 *
 * CSRF is exempt for these routes (see bootstrap/app.php); authenticity comes
 * from the signature instead. An unsigned or badly signed request is dropped
 * without touching a wallet.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly PaystackGateway $paystack,
        private readonly FlutterwaveGateway $flutterwave,
    ) {}

    public function paystack(Request $request): Response
    {
        $raw = $request->getContent();

        if (! $this->paystack->signatureIsValid($raw, $request->header('x-paystack-signature'))) {
            Log::warning('Rejected Paystack webhook with an invalid signature.', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $reference = data_get($payload, 'data.reference');

        Log::info('[PAYSTACK_WEBHOOK] Received Payload', [
            'ip' => $request->ip(),
            'event' => data_get($payload, 'event'),
            'reference' => $reference,
            'payload' => $payload,
        ]);

        if (data_get($payload, 'event') === 'charge.success' && $reference) {
            // Re-verify against the API rather than trusting the payload's
            // amount, then let DepositService apply its own idempotency guard.
            $this->deposits->handleWebhook('paystack', $this->paystack->verify($reference));
        }

        // Always 200 on a validly signed request: a non-2xx makes Paystack
        // retry, and a retry storm over an event we chose to ignore is noise.
        return response('OK', 200);
    }

    public function flutterwave(Request $request): Response
    {
        $raw = $request->getContent();

        if (! $this->flutterwave->signatureIsValid($raw, $request->header('verif-hash'))) {
            Log::warning('Rejected Flutterwave webhook with an invalid hash.', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $reference = data_get($payload, 'data.tx_ref') ?? data_get($payload, 'txRef');
        $transactionId = data_get($payload, 'data.id');

        Log::info('[FLUTTERWAVE_WEBHOOK] Received Payload', [
            'ip' => $request->ip(),
            'event' => data_get($payload, 'event'),
            'reference' => $reference,
            'transaction_id' => $transactionId,
            'payload' => $payload,
        ]);

        if ($reference || $transactionId) {
            $this->deposits->handleWebhook(
                'flutterwave',
                $this->flutterwave->verify((string) ($transactionId ?: $reference)),
            );
        }

        return response('OK', 200);
    }
}
