<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $appointments = $request->user()->appointments()
            ->with(['lawyer.user', 'service', 'notarialDocument'])
            ->orderByDesc('scheduled_date')->orderByDesc('start_time')
            ->get();

        $next = $appointments
            ->filter(fn (Appointment $appointment) => $appointment->status->isCancellable() && $appointment->isUpcoming())
            ->sortBy(fn (Appointment $appointment) => $appointment->startsAt())
            ->first();

        return view('client.appointments.index', compact('appointments', 'next'));
    }

    public function show(Appointment $appointment): View
    {
        Gate::authorize('view', $appointment);
        $appointment->load(['lawyer.user', 'service', 'notarialDocument', 'attachments', 'notarialEntry']);

        return view('client.appointments.show', compact('appointment'));
    }

    public function cancel(Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);
        $workflow->cancel($appointment);

        return back()->with('status', "{$appointment->reference} was cancelled.");
    }
}
