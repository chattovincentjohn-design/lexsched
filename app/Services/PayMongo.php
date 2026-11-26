<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the PayMongo API: creates hosted checkout pages, reads them back and verifies webhooks.
 *
 * @see https://developers.paymongo.com/docs/checkout-api
 */
class PayMongo
{
    public const BASE_URL = 'https://api.paymongo.com/v1';

    public function isEnabled(): bool
    {
        return filled(config('lexsched.paymongo.secret_key'));
    }

    /**
     * Creates a checkout session for the appointment fee and returns PayMongo's hosted payment page.
     *
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckout(Appointment $appointment): array
    {
        $appointment->loadMissing(['service', 'notarialDocument', 'lawyer.user']);
        $item = $appointment->notarialDocument
            ? "{$appointment->notarialDocument->name} ({$appointment->copies} ".str('copy')->plural($appointment->copies).')'
            : "{$appointment->service->name} consultation";

        $response = $this->request()->post('/checkout_sessions', [
            'data' => ['attributes' => [
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount' => (int) round((float) $appointment->fee * 100),
                    'name' => $item,
                    'description' => "{$appointment->reference} · {$appointment->scheduleLabel()} · {$appointment->lawyer->name}",
                    'quantity' => 1,
                ]],
                'payment_method_types' => config('lexsched.paymongo.methods'),
                'description' => config('lexsched.office_name')." – {$appointment->reference}",
                'reference_number' => $appointment->reference,
                'success_url' => route('client.appointments.show', ['appointment' => $appointment, 'payment' => 'success']),
                'cancel_url' => route('client.appointments.show', ['appointment' => $appointment, 'payment' => 'cancelled']),
                'billing' => array_filter([
                    'name' => $appointment->client_name,
                    'email' => $appointment->client_email,
                    'phone' => $appointment->client_phone,
                ]),
                'send_email_receipt' => filled($appointment->client_email),
                'show_description' => true,
                'show_line_items' => true,
                'metadata' => ['appointment_reference' => $appointment->reference],
            ]],
        ])->throw();

        return [
            'id' => $response->json('data.id'),
            'checkout_url' => $response->json('data.attributes.checkout_url'),
        ];
    }

    /**
     * The paid payment of a checkout session, or null if the client has not paid yet.
     *
     * @return array{id: string, method: string}|null
     */
    public function paidPayment(string $checkoutId): ?array
    {
        $session = $this->request()->get("/checkout_sessions/{$checkoutId}")->throw()->json('data');

        return $this->paidPaymentFrom($session);
    }

    /**
     * Reads the paid payment out of a checkout session resource (from the API or from a webhook).
     *
     * @param  array<string, mixed>  $session
     * @return array{id: string, method: string}|null
     */
    public function paidPaymentFrom(array $session): ?array
    {
        foreach ($session['attributes']['payments'] ?? [] as $payment) {
            if (($payment['attributes']['status'] ?? null) === 'paid') {
                return [
                    'id' => $payment['id'],
                    'method' => $this->localMethod($payment['attributes']['source']['type'] ?? null),
                ];
            }
        }

        return null;
    }

    /**
     * Checks the Paymongo-Signature header: "t=<timestamp>,te=<test signature>,li=<live signature>".
     */
    public function hasValidSignature(?string $header, string $payload, bool $livemode): bool
    {
        $secret = config('lexsched.paymongo.webhook_secret');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

                return [$key => $value];
            });

        $timestamp = (int) $parts->get('t');
        $signature = (string) $parts->get($livemode ? 'li' : 'te');

        if ($timestamp === 0 || $signature === '' || abs(now()->timestamp - $timestamp) > config('lexsched.paymongo.signature_tolerance_seconds')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$timestamp}.{$payload}", $secret), $signature);
    }

    /**
     * Registers this app's webhook with PayMongo and returns its signing secret.
     */
    public function registerWebhook(string $url): string
    {
        try {
            return $this->request()->post('/webhooks', [
                'data' => ['attributes' => ['url' => $url, 'events' => ['checkout_session.payment.paid']]],
            ])->throw()->json('data.attributes.secret_key');
        } catch (RequestException $exception) {
            throw new RuntimeException('PayMongo refused the webhook: '.($exception->response->json('errors.0.detail') ?? $exception->getMessage()));
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth((string) config('lexsched.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function localMethod(?string $paymongoType): string
    {
        return match ($paymongoType) {
            'gcash' => 'gcash',
            'paymaya' => 'maya',
            'card' => 'card',
            default => 'online',
        };
    }
}
