<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawyerScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
    }

    public function test_lawyer_sees_their_week_and_can_block_an_hour(): void
    {
        $lawyerUser = $this->lawyer->user;

        $this->actingAs($lawyerUser)->get(route('lawyer.schedule'))->assertOk()->assertSee('My schedule');
        $this->actingAs($lawyerUser)->post(route('lawyer.blocks.store'), ['date' => '2026-12-03', 'time' => '14:00', 'reason' => 'Court hearing'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('blocked_slots', ['lawyer_id' => $this->lawyer->id, 'start_time' => '14:00']);
    }

    public function test_lawyer_cannot_block_an_hour_that_is_already_booked(): void
    {
        Appointment::factory()->create(['lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'scheduled_date' => '2026-12-03', 'start_time' => '14:00']);

        $this->actingAs($this->lawyer->user)
            ->post(route('lawyer.blocks.store'), ['date' => '2026-12-03', 'time' => '14:00', 'reason' => 'Court hearing'])
            ->assertSessionHasErrors('block');
    }

    public function test_lawyer_cannot_remove_another_lawyers_block(): void
    {
        $block = BlockedSlot::create(['lawyer_id' => $this->notary->id, 'date' => '2026-12-03', 'start_time' => null, 'reason' => 'On leave']);

        $this->actingAs($this->lawyer->user)->delete(route('lawyer.blocks.destroy', $block))->assertForbidden();
    }

    public function test_notes_are_private_to_the_assigned_lawyer(): void
    {
        $appointment = Appointment::factory()->create(['lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'status' => AppointmentStatus::Approved]);

        $this->actingAs($this->lawyer->user)->patch(route('lawyer.appointments.notes', $appointment), ['lawyer_notes' => 'Bring marriage certificate.'])->assertSessionHasNoErrors();
        $this->actingAs($this->notary->user)->patch(route('lawyer.appointments.notes', $appointment), ['lawyer_notes' => 'x'])->assertForbidden();

        $this->assertSame('Bring marriage certificate.', $appointment->fresh()->lawyer_notes);
    }
}
