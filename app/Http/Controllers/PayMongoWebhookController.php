<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use App\Services\PayMongo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives "checkout_session.payment.paid" events from PayMongo and marks the appointment paid.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongo $payMongo, AppointmentWorkflow $workflow): JsonResponse
    {
        $event = $request->json('data.attributes', []);

        if (! $payMongo->hasValidSignature($request->header('Paymongo-Signature'), $request->getContent(), (bool) ($event['livemode'] ?? false))) {
            Log::warning('Rejected PayMongo webhook with an invalid signature.');

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (($event['type'] ?? null) !== 'checkout_session.payment.paid') {
            return response()->json(['message' => 'Ignored.']);
        }

        $session = $event['data'] ?? [];
        $appointment = Appointment::query()
            ->where('paymongo_checkout_id', $session['id'] ?? '')
            ->orWhere('reference', $session['attributes']['reference_number'] ?? '')
            ->first();
        $payment = $payMongo->paidPaymentFrom($session);

        if ($appointment && $payment) {
            $workflow->recordOnlinePayment($appointment, $payment['id'], $payment['method']);
        }

        return response()->json(['message' => 'Received.']);
    }
}
