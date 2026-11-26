<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Services\AppointmentWorkflow;
use App\Services\AvailabilityService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lets a client (within the cutoff) or the secretary move an appointment to another schedule.
 */
class RescheduleController extends Controller
{
    public function edit(Request $request, Appointment $appointment): View
    {
        Gate::authorize('reschedule', $appointment);
        $appointment->load(['service', 'lawyer.user', 'notarialDocument']);

        return view('reschedule', [
            'appointment' => $appointment,
            'byStaff' => $this->isStaff($request),
            'action' => $this->isStaff($request) ? route('staff.appointments.reschedule.update', $appointment) : route('client.appointments.reschedule.update', $appointment),
            'back' => $this->isStaff($request) ? route('staff.appointments.index', ['filter' => 'all', 'selected' => $appointment->reference]) : route('client.appointments.show', $appointment),
        ]);
    }

    public function update(Request $request, Appointment $appointment, AppointmentWorkflow $workflow, AvailabilityService $availability): RedirectResponse
    {
        Gate::authorize('reschedule', $appointment);
        $byStaff = $this->isStaff($request);

        $data = $request->validate([
            'lawyer_id' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', Rule::in(config('lexsched.slots'))],
        ], ['time.required' => 'Pick a date and an open time.']);

        $lawyer = $data['lawyer_id'] === 'any'
            ? Lawyer::find(($byStaff ? $availability->forWalkIns() : $availability)->openSlotsForService($appointment->service, null, $data['date'])->get($data['time'], [])[0] ?? null)
            : Lawyer::find((int) $data['lawyer_id']);

        try {
            throw_unless($lawyer, DomainException::class, 'No lawyer is free at that time. Please choose another.');
            $workflow->reschedule($appointment, $lawyer, $data['date'], $data['time'], $byStaff);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['time' => $exception->getMessage()]);
        }

        if ($byStaff) {
            return redirect()->route('staff.appointments.index', ['filter' => 'all', 'selected' => $appointment->reference])
                ->with('status', "{$appointment->reference} moved to {$appointment->scheduleLabel()}. The client was notified.");
        }

        return redirect()->route('client.appointments.show', $appointment)
            ->with('status', "Moved to {$appointment->scheduleLabel()}. The secretary will confirm the new schedule.");
    }

    private function isStaff(Request $request): bool
    {
        return $request->user()->hasRole(UserRole::Secretary, UserRole::Admin);
    }
}
