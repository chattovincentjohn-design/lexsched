<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use App\Services\PesoWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
    }

    public function test_receipt_shows_the_amount_in_words_for_the_owner_and_staff_only(): void
    {
        $client = $this->user(UserRole::Client);
        $appointment = Appointment::factory()->create([
            'client_id' => $client->id, 'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id,
            'fee' => 1550.50, 'paid_at' => now(), 'payment_method' => 'gcash', 'official_receipt_no' => 'OR-0042',
        ]);

        $this->actingAs($client)->get(route('appointments.receipt', $appointment))
            ->assertOk()
            ->assertSee('₱1,550.50')
            ->assertSee('One thousand five hundred fifty pesos and 50/100')
            ->assertSee('OR-0042');
        $this->actingAs($this->user(UserRole::Secretary))->get(route('appointments.receipt', $appointment))->assertOk();
        $this->actingAs($this->user(UserRole::Client))->get(route('appointments.receipt', $appointment))->assertForbidden();
    }

    public function test_unpaid_appointments_have_no_receipt(): void
    {
        $client = $this->user(UserRole::Client);
        $appointment = Appointment::factory()->create(['client_id' => $client->id, 'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id]);

        $this->actingAs($client)->get(route('appointments.receipt', $appointment))->assertNotFound()->assertSee('Page not found');
    }

    public function test_peso_words(): void
    {
        $this->assertSame('Three hundred pesos only', PesoWords::format(300));
        $this->assertSame('Twenty-one pesos only', PesoWords::format(21));
        $this->assertSame('One million two thousand five hundred pesos only', PesoWords::format(1002500));
        $this->assertSame('One peso only', PesoWords::format(1));
    }

    public function test_day_calendar_shows_every_lawyer_with_bookings_and_blocks(): void
    {
        Appointment::factory()->create(['client_name' => 'Ana Lim', 'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'scheduled_date' => '2026-12-02', 'start_time' => '09:00', 'status' => AppointmentStatus::Approved]);
        BlockedSlot::create(['lawyer_id' => $this->notary->id, 'date' => '2026-12-02', 'start_time' => null, 'reason' => 'On leave']);

        $this->actingAs($this->user(UserRole::Secretary))
            ->get(route('staff.calendar', ['date' => '2026-12-02']))
            ->assertOk()
            ->assertSee($this->lawyer->name)
            ->assertSee($this->notary->name)
            ->assertSee('Ana Lim')
            ->assertSee('On leave')
            ->assertSee('+ Walk-in');
    }

    public function test_day_calendar_marks_holidays_closed(): void
    {
        $this->actingAs($this->user(UserRole::Secretary))
            ->get(route('staff.calendar', ['date' => '2026-12-08']))
            ->assertSee('Feast of the Immaculate Conception')
            ->assertDontSee('+ Walk-in');
    }
}
