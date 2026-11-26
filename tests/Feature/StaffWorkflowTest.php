<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Services\ConflictChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StaffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
    }

    private function pending(array $attributes = []): Appointment
    {
        return Appointment::factory()->create([
            'lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id,
            'scheduled_date' => '2026-12-02', 'start_time' => '10:00', ...$attributes,
        ]);
    }

    public function test_secretary_approves_a_request_and_the_client_gets_a_text(): void
    {
        Log::spy();
        $appointment = $this->pending(['client_phone' => '09171234567']);

        $this->actingAs($this->user(UserRole::Secretary))
            ->post(route('staff.appointments.approve', $appointment))
            ->assertRedirect(route('staff.appointments.index', ['selected' => $appointment->reference]));

        $this->assertSame(AppointmentStatus::Approved, $appointment->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'approved')->exists());
        Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'SMS to 09171234567') && str_contains($message, 'CONFIRMED'));
    }

    public function test_declining_frees_the_slot_and_keeps_the_reason(): void
    {
        $appointment = $this->pending();

        $this->actingAs($this->user(UserRole::Secretary))
            ->post(route('staff.appointments.decline', $appointment), ['reason' => 'Lawyer unavailable']);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Declined, $appointment->status);
        $this->assertNull($appointment->slot_key);
        $this->assertSame('Lawyer unavailable', $appointment->decline_reason);
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $appointment = $this->pending(['status' => AppointmentStatus::Completed]);

        $this->actingAs($this->user(UserRole::Secretary))
            ->post(route('staff.appointments.approve', $appointment))
            ->assertSessionHasErrors('action');

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_no_show_only_after_the_appointment_time(): void
    {
        $appointment = $this->pending(['status' => AppointmentStatus::Approved]);
        $secretary = $this->user(UserRole::Secretary);

        $this->actingAs($secretary)->post(route('staff.appointments.no-show', $appointment))->assertSessionHasErrors('action');

        $this->travelTo(now()->addDays(2));
        $this->actingAs($secretary)->post(route('staff.appointments.no-show', $appointment))->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
    }

    public function test_conflict_checker_flags_both_directions(): void
    {
        $this->pending(['client_name' => 'Ramon Tan', 'opposing_party' => 'Benjamin Flores', 'start_time' => '09:00']);
        $request = $this->pending(['client_name' => 'Benjamin Flores', 'opposing_party' => 'ramon  TAN']);
        $clean = $this->pending(['client_name' => 'Ana Lim', 'opposing_party' => 'Someone Else', 'start_time' => '11:00']);

        $findings = app(ConflictChecker::class)->check($request);

        $this->assertCount(2, $findings);
        $this->assertStringContainsString('Ramon Tan (the opposing party) is an existing client', $findings[0]);
        $this->assertSame([], app(ConflictChecker::class)->check($clean));
    }

    public function test_recording_payment_marks_it_paid_once(): void
    {
        $appointment = $this->pending(['status' => AppointmentStatus::Approved]);
        $secretary = $this->user(UserRole::Secretary);

        $this->actingAs($secretary)->post(route('staff.appointments.payment', $appointment), ['method' => 'cash', 'official_receipt_no' => 'OR-1201'])->assertSessionHasNoErrors();
        $this->assertTrue($appointment->fresh()->isPaid());
        $this->assertSame('OR-1201', $appointment->fresh()->official_receipt_no);

        $this->actingAs($secretary)->post(route('staff.appointments.payment', $appointment), ['method' => 'cash'])->assertSessionHasErrors('action');
    }

    public function test_walk_in_is_approved_immediately_and_can_use_the_current_hour(): void
    {
        $this->travelTo(now()->setTime(10, 20));

        $this->actingAs($this->user(UserRole::Secretary))->post(route('staff.walk-in.store'), [
            'client_name' => 'Josefina Ramos', 'client_phone' => '09181234567', 'service_id' => $this->notarial->id,
            'lawyer_id' => 'any', 'date' => '2026-12-01', 'time' => '10:00', 'notarial_document_id' => $this->affidavit->id, 'copies' => 1,
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::sole();
        $this->assertSame(AppointmentStatus::Approved, $appointment->status);
        $this->assertSame('walk_in', $appointment->source);
        $this->assertSame($this->notary->id, $appointment->lawyer_id);
    }

    public function test_roles_cannot_open_each_others_pages(): void
    {
        $this->get(route('client.book'))->assertRedirect(route('login'));
        $this->actingAs($this->user(UserRole::Client))->get(route('staff.appointments.index'))->assertForbidden();
        $this->actingAs($this->user(UserRole::Secretary))->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->user(UserRole::Lawyer))->get(route('staff.register'))->assertForbidden();
        $this->actingAs($this->user(UserRole::Admin))->get(route('staff.appointments.index'))->assertOk();
    }

    public function test_staff_pages_render(): void
    {
        $this->pending();
        $secretary = $this->user(UserRole::Secretary);
        $admin = $this->user(UserRole::Admin);

        $this->actingAs($secretary)->get(route('staff.appointments.index'))->assertOk()->assertSee('No conflict found');
        $this->actingAs($secretary)->get(route('staff.walk-in.create'))->assertOk();
        $this->actingAs($secretary)->get(route('staff.register'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.services.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.appointments', ['from' => '2026-12-01', 'to' => '2026-12-31']))->assertOk();
    }
}
