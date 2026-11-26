<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
        $this->client = $this->user(UserRole::Client);
        $this->appointment = Appointment::factory()->create([
            'client_id' => $this->client->id, 'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id,
            'scheduled_date' => '2026-12-03', 'start_time' => '10:00', 'status' => AppointmentStatus::Approved,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function move(array $overrides = []): array
    {
        return ['lawyer_id' => (string) $this->lawyer->id, 'date' => '2026-12-04', 'time' => '14:00', ...$overrides];
    }

    public function test_client_moves_an_approved_appointment_and_it_needs_approval_again(): void
    {
        $this->actingAs($this->client)->get(route('client.appointments.reschedule', $this->appointment))->assertOk();

        $this->actingAs($this->client)
            ->put(route('client.appointments.reschedule.update', $this->appointment), $this->move())
            ->assertRedirect(route('client.appointments.show', $this->appointment));

        $moved = $this->appointment->fresh();
        $this->assertSame('2026-12-04', $moved->scheduled_date->toDateString());
        $this->assertSame('14:00', $moved->start_time);
        $this->assertSame(AppointmentStatus::Pending, $moved->status);
        $this->assertSame(1, $moved->reschedule_count);
        $this->assertSame("{$this->lawyer->id}|2026-12-04|14:00", $moved->slot_key);
    }

    public function test_the_old_slot_becomes_free_again(): void
    {
        $this->actingAs($this->client)->put(route('client.appointments.reschedule.update', $this->appointment), $this->move());

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload(['date' => '2026-12-03', 'time' => '10:00']))
            ->assertSessionHasNoErrors();
    }

    public function test_clients_cannot_move_within_the_cutoff_or_too_many_times(): void
    {
        $this->appointment->update(['reschedule_count' => 2]);
        $this->actingAs($this->client)->get(route('client.appointments.reschedule', $this->appointment))->assertForbidden();

        $this->appointment->update(['reschedule_count' => 0]);
        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(12, 0));
        $this->actingAs($this->client)->put(route('client.appointments.reschedule.update', $this->appointment), $this->move(['date' => '2026-12-07']))
            ->assertForbidden();
    }

    public function test_taken_or_unchanged_times_are_refused(): void
    {
        Appointment::factory()->create(['lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'scheduled_date' => '2026-12-04', 'start_time' => '14:00']);

        $this->actingAs($this->client)->put(route('client.appointments.reschedule.update', $this->appointment), $this->move())->assertSessionHasErrors('time');
        $this->actingAs($this->client)->put(route('client.appointments.reschedule.update', $this->appointment), $this->move(['date' => '2026-12-03', 'time' => '10:00']))->assertSessionHasErrors('time');

        $this->assertSame('2026-12-03', $this->appointment->fresh()->scheduled_date->toDateString());
    }

    public function test_other_clients_cannot_move_it(): void
    {
        $this->actingAs($this->user(UserRole::Client))->get(route('client.appointments.reschedule', $this->appointment))->assertForbidden();
    }

    public function test_secretary_moves_it_to_another_lawyer_and_it_stays_approved(): void
    {
        $this->actingAs($this->user(UserRole::Secretary))
            ->put(route('staff.appointments.reschedule.update', $this->appointment), $this->move(['lawyer_id' => (string) $this->notary->id, 'date' => '2026-12-01', 'time' => '09:00']))
            ->assertSessionHasNoErrors();

        $moved = $this->appointment->fresh();
        $this->assertSame($this->notary->id, $moved->lawyer_id);
        $this->assertSame(AppointmentStatus::Approved, $moved->status);
        $this->assertSame(0, $moved->reschedule_count);
    }
}
