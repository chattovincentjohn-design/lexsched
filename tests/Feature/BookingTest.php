<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
    }

    public function test_client_can_book_an_open_slot_and_it_starts_pending(): void
    {
        $client = $this->user(UserRole::Client);

        $response = $this->actingAs($client)->post(route('client.book.store'), $this->bookingPayload());

        $appointment = Appointment::sole();
        $response->assertRedirect(route('client.appointments.show', $appointment));
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame($client->id, $appointment->client_id);
        $this->assertSame($client->phone, $appointment->client_phone);
        $this->assertSame("{$this->lawyer->id}|2026-12-02|10:00", $appointment->slot_key);
        $this->assertEquals(1500, $appointment->fee);
    }

    public function test_the_same_slot_cannot_be_booked_twice(): void
    {
        $this->actingAs($this->user(UserRole::Client))->post(route('client.book.store'), $this->bookingPayload());

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload())
            ->assertSessionHasErrors('time');

        $this->assertSame(1, Appointment::count());
    }

    public function test_a_cancelled_appointment_frees_its_slot(): void
    {
        $client = $this->user(UserRole::Client);
        $this->actingAs($client)->post(route('client.book.store'), $this->bookingPayload());
        $this->actingAs($client)->post(route('client.appointments.cancel', Appointment::sole()))->assertSessionHasNoErrors();

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::whereNotNull('slot_key')->count());
    }

    public function test_weekends_holidays_and_past_hours_cannot_be_booked(): void
    {
        $client = $this->user(UserRole::Client);

        foreach ([['date' => '2026-12-05'], ['date' => '2026-12-08'], ['date' => '2026-12-01', 'time' => '08:00'], ['date' => '2026-11-30']] as $override) {
            $this->actingAs($client)->post(route('client.book.store'), $this->bookingPayload($override))->assertSessionHasErrors();
        }

        $this->assertSame(0, Appointment::count());
    }

    public function test_blocked_hours_cannot_be_booked(): void
    {
        BlockedSlot::create(['lawyer_id' => $this->lawyer->id, 'date' => '2026-12-02', 'start_time' => '10:00', 'reason' => 'Court hearing']);

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload())
            ->assertSessionHasErrors('time');
    }

    public function test_any_available_lawyer_picks_someone_who_is_free(): void
    {
        BlockedSlot::create(['lawyer_id' => $this->lawyer->id, 'date' => '2026-12-02', 'start_time' => null, 'reason' => 'On leave']);

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload(['lawyer_id' => 'any']))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->notary->id, Appointment::sole()->lawyer_id);
    }

    public function test_notarial_booking_uses_the_document_fee_plus_extra_copies_and_is_in_office(): void
    {
        $this->actingAs($this->user(UserRole::Client))->post(route('client.book.store'), $this->bookingPayload([
            'service_id' => $this->notarial->id,
            'lawyer_id' => (string) $this->notary->id,
            'notarial_document_id' => $this->affidavit->id,
            'copies' => 3,
            'concern' => '',
            'mode' => 'video',
        ]))->assertSessionHasNoErrors();

        $appointment = Appointment::sole();
        $this->assertEquals(400, $appointment->fee);
        $this->assertSame('in_office', $appointment->mode);
        $this->assertNull($appointment->opposing_party);
    }

    public function test_notarial_booking_requires_a_commissioned_notary(): void
    {
        $this->actingAs($this->user(UserRole::Client))->post(route('client.book.store'), $this->bookingPayload([
            'service_id' => $this->notarial->id,
            'lawyer_id' => (string) $this->lawyer->id,
            'notarial_document_id' => $this->affidavit->id,
        ]))->assertSessionHasErrors('lawyer_id');
    }

    public function test_consent_and_concern_are_required_and_ewallet_needs_a_reference(): void
    {
        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload(['consent' => null, 'concern' => '', 'payment_method' => 'gcash']))
            ->assertSessionHasErrors(['consent', 'concern', 'payment_reference']);
    }

    public function test_attachment_is_stored_privately_and_only_visible_to_its_owner_and_staff(): void
    {
        Storage::fake('local');
        $owner = $this->user(UserRole::Client);

        $this->actingAs($owner)->post(route('client.book.store'), $this->bookingPayload([
            'attachment' => UploadedFile::fake()->create('subpoena.pdf', 200, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $attachment = Appointment::sole()->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($owner)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($this->user(UserRole::Secretary))->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($this->user(UserRole::Client))->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_clients_cannot_see_other_clients_appointments(): void
    {
        $this->actingAs($this->user(UserRole::Client))->post(route('client.book.store'), $this->bookingPayload());

        $this->actingAs($this->user(UserRole::Client))
            ->get(route('client.appointments.show', Appointment::sole()))
            ->assertForbidden();
    }

    public function test_availability_endpoint_lists_open_days_and_slots(): void
    {
        BlockedSlot::create(['lawyer_id' => $this->lawyer->id, 'date' => '2026-12-02', 'start_time' => '09:00', 'reason' => 'Court hearing']);

        $response = $this->actingAs($this->user(UserRole::Client))
            ->getJson(route('availability', ['service' => $this->family->id, 'lawyer' => $this->lawyer->id, 'month' => '2026-12', 'date' => '2026-12-02']))
            ->assertOk();

        $days = $response->json('days');
        $this->assertContains('2026-12-01', $days);
        $this->assertNotContains('2026-12-05', $days);
        $this->assertNotContains('2026-12-08', $days);
        $this->assertFalse(collect($response->json('slots'))->firstWhere('time', '09:00')['open']);
        $this->assertTrue(collect($response->json('slots'))->firstWhere('time', '10:00')['open']);
    }
}
