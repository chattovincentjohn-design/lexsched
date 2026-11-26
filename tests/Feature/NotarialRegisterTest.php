<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\NotarialEntry;
use App\Services\NotarialRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotarialRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
    }

    private function approvedNotarial(Lawyer $notary, string $time, bool $checked = true): Appointment
    {
        return Appointment::factory()->create([
            'lawyer_id' => $notary->id, 'service_id' => $this->notarial->id, 'notarial_document_id' => $this->affidavit->id,
            'scheduled_date' => '2026-12-01', 'start_time' => $time, 'status' => AppointmentStatus::Approved, 'fee' => 300,
            'check_appeared' => $checked, 'check_id_presented' => $checked, 'check_document_complete' => $checked,
            'id_type' => $checked ? 'PhilSys National ID' : null,
        ]);
    }

    public function test_notarize_is_refused_until_the_checklist_is_complete(): void
    {
        $appointment = $this->approvedNotarial($this->notary, '09:00', checked: false);

        $this->actingAs($this->user(UserRole::Secretary))
            ->post(route('staff.appointments.notarize', $appointment))
            ->assertSessionHasErrors('action');

        $this->assertSame(0, NotarialEntry::count());
    }

    public function test_checklist_can_be_saved_and_then_notarized(): void
    {
        $appointment = $this->approvedNotarial($this->notary, '09:00', checked: false);
        $secretary = $this->user(UserRole::Secretary);

        $this->actingAs($secretary)->post(route('staff.appointments.checklist', $appointment), [
            'check_appeared' => '1', 'check_id_presented' => '1', 'check_document_complete' => '1', 'id_type' => 'Passport',
        ])->assertSessionHasNoErrors();

        $this->actingAs($secretary)->post(route('staff.appointments.notarize', $appointment))->assertSessionHasNoErrors();

        $entry = NotarialEntry::sole();
        $this->assertSame('Doc. No. 1; Page No. 1; Book No. I; Series of 2026', $entry->certificateLine());
        $this->assertSame('Passport', $entry->id_type);
        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
        $this->assertTrue($appointment->fresh()->isPaid());
    }

    public function test_each_notary_numbers_their_own_register_in_sequence(): void
    {
        $register = app(NotarialRegister::class);
        $otherNotary = Lawyer::factory()->notary()->offering($this->notarial)->create();

        $first = $register->record($this->approvedNotarial($this->notary, '09:00'));
        $second = $register->record($this->approvedNotarial($this->notary, '10:00'));
        $other = $register->record($this->approvedNotarial($otherNotary, '09:00'));

        $this->assertSame([1, 2, 1], [$first->doc_no, $second->doc_no, $other->doc_no]);
    }

    public function test_page_and_book_numbers_follow_the_register_layout(): void
    {
        $register = app(NotarialRegister::class);

        $this->assertSame(48, $register->pageFor(238));
        $this->assertSame('III', $register->bookFor(238));
        $this->assertSame(1, $register->pageFor(5));
        $this->assertSame(2, $register->pageFor(6));
        $this->assertSame('I', $register->bookFor(100));
        $this->assertSame('II', $register->bookFor(101));
    }

    public function test_expired_commission_cannot_notarize(): void
    {
        $this->notary->update(['commission_expires_on' => '2026-11-30']);

        $this->actingAs($this->user(UserRole::Secretary))
            ->post(route('staff.appointments.notarize', $this->approvedNotarial($this->notary, '09:00')))
            ->assertSessionHasErrors('action');
    }

    public function test_notarial_appointments_cannot_be_completed_without_the_register(): void
    {
        $this->actingAs($this->user(UserRole::Secretary))
            ->post(route('staff.appointments.complete', $this->approvedNotarial($this->notary, '09:00')))
            ->assertSessionHasErrors('action');
    }
}
