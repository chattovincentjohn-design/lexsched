<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayMongoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
        config(['lexsched.paymongo.secret_key' => 'sk_test_123', 'lexsched.paymongo.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function fakeCheckout(array $session = []): void
    {
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => [
                'id' => 'cs_test_abc', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_abc'],
            ]]),
            'api.paymongo.com/v1/checkout_sessions/*' => Http::response(['data' => $session]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paidSession(string $reference, string $type = 'gcash'): array
    {
        return ['id' => 'cs_test_abc', 'type' => 'checkout_session', 'attributes' => [
            'reference_number' => $reference,
            'payments' => [['id' => 'pay_test_789', 'attributes' => ['status' => 'paid', 'amount' => 150000, 'source' => ['type' => $type]]]],
        ]];
    }

    private function bookOnline(User $client): Appointment
    {
        $this->actingAs($client)->post(route('client.book.store'), $this->bookingPayload(['payment_method' => 'online']));

        return Appointment::sole();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: string, 1: string}
     */
    private function signed(array $body, ?string $secret = null): array
    {
        $payload = json_encode($body);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::WEBHOOK_SECRET);

        return [$payload, "t={$timestamp},te={$signature},li="];
    }

    private function postWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ], $payload);
    }

    public function test_paying_online_sends_the_client_to_paymongo_checkout_with_the_fee(): void
    {
        $this->fakeCheckout();
        $client = $this->user(UserRole::Client);

        $this->actingAs($client)
            ->post(route('client.book.store'), $this->bookingPayload(['payment_method' => 'online']))
            ->assertRedirect('https://checkout.paymongo.com/cs_test_abc');

        $appointment = Appointment::sole();
        $this->assertSame('cs_test_abc', $appointment->paymongo_checkout_id);
        $this->assertFalse($appointment->isPaid());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $request['data']['attributes']['line_items'][0]['amount'] === 150000
            && $request['data']['attributes']['reference_number'] === $appointment->reference
            && $request['data']['attributes']['payment_method_types'] === ['gcash', 'paymaya', 'card']
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_123:')));
    }

    public function test_webhook_with_a_valid_signature_marks_the_appointment_paid_once(): void
    {
        $this->fakeCheckout();
        $appointment = $this->bookOnline($this->user(UserRole::Client));
        $body = ['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => $this->paidSession($appointment->reference, 'paymaya')]]];
        [$payload, $signature] = $this->signed($body);

        $this->postWebhook($payload, $signature)->assertOk();
        $this->postWebhook($payload, $signature)->assertOk();

        $appointment->refresh();
        $this->assertTrue($appointment->isPaid());
        $this->assertSame('maya', $appointment->payment_method);
        $this->assertSame('pay_test_789', $appointment->paymongo_payment_id);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_webhook_with_a_bad_or_stale_signature_is_rejected(): void
    {
        $this->fakeCheckout();
        $appointment = $this->bookOnline($this->user(UserRole::Client));
        $body = ['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => $this->paidSession($appointment->reference)]]];

        [$payload, $forged] = $this->signed($body, 'wrong-secret');
        $this->postWebhook($payload, $forged)->assertUnauthorized();

        [$payload, $signature] = $this->signed($body);
        $this->travel(10)->minutes();
        $this->postWebhook($payload, $signature)->assertUnauthorized();

        $this->assertFalse($appointment->fresh()->isPaid());
    }

    public function test_returning_from_checkout_confirms_payment_with_paymongo(): void
    {
        $this->fakeCheckout();
        $client = $this->user(UserRole::Client);
        $appointment = $this->bookOnline($client);
        $this->fakeCheckoutPaid($appointment);

        $this->actingAs($client)
            ->get(route('client.appointments.show', ['appointment' => $appointment, 'payment' => 'success']))
            ->assertOk()
            ->assertSee('Payment received');

        $this->assertTrue($appointment->fresh()->isPaid());
        $this->assertSame('gcash', $appointment->fresh()->payment_method);
    }

    public function test_a_success_url_alone_does_not_mark_anything_paid(): void
    {
        $this->fakeCheckout(['id' => 'cs_test_abc', 'attributes' => ['payments' => []]]);
        $client = $this->user(UserRole::Client);
        $appointment = $this->bookOnline($client);

        $this->actingAs($client)
            ->get(route('client.appointments.show', ['appointment' => $appointment, 'payment' => 'success']))
            ->assertSee('waiting for PayMongo');

        $this->assertFalse($appointment->fresh()->isPaid());
    }

    public function test_client_can_pay_later_for_an_unpaid_booking(): void
    {
        $this->fakeCheckout();
        $client = $this->user(UserRole::Client);
        $this->actingAs($client)->post(route('client.book.store'), $this->bookingPayload(['payment_method' => 'office']));
        $appointment = Appointment::sole();

        $this->actingAs($client)->get(route('client.appointments.show', $appointment))->assertSee('online');
        $this->actingAs($client)
            ->post(route('client.appointments.pay', $appointment))
            ->assertRedirect('https://checkout.paymongo.com/cs_test_abc');

        $this->assertSame('online', $appointment->fresh()->payment_method);
    }

    public function test_paid_or_cancelled_appointments_cannot_be_paid_again(): void
    {
        $client = $this->user(UserRole::Client);
        $paid = Appointment::factory()->create(['client_id' => $client->id, 'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'paid_at' => now()]);
        $cancelled = Appointment::factory()->create(['client_id' => $client->id, 'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'start_time' => '11:00', 'status' => AppointmentStatus::Cancelled]);

        $this->actingAs($client)->post(route('client.appointments.pay', $paid))->assertStatus(422);
        $this->actingAs($client)->post(route('client.appointments.pay', $cancelled))->assertStatus(422);
    }

    public function test_booking_is_kept_when_paymongo_is_down(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response(['errors' => [['detail' => 'Service unavailable']]], 503)]);
        $client = $this->user(UserRole::Client);

        $this->actingAs($client)->post(route('client.book.store'), $this->bookingPayload(['payment_method' => 'online']))
            ->assertRedirect(route('client.appointments.show', Appointment::sole()))
            ->assertSessionHasErrors('action');

        $this->assertSame(AppointmentStatus::Pending, Appointment::sole()->status);
    }

    public function test_online_option_is_only_offered_when_paymongo_is_configured(): void
    {
        config(['lexsched.paymongo.secret_key' => null]);

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload(['payment_method' => 'online']))
            ->assertSessionHasErrors('payment_method');
    }

    private function fakeCheckoutPaid(Appointment $appointment): void
    {
        Http::swap(new Factory);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response(['data' => $this->paidSession($appointment->reference)])]);
    }
}
