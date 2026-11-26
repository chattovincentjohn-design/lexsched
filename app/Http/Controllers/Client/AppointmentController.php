<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use App\Services\PayMongo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

    public function show(Request $request, Appointment $appointment, PayMongo $payMongo, AppointmentWorkflow $workflow): View
    {
        Gate::authorize('view', $appointment);

        if ($request->query('payment') === 'success' && ! $appointment->isPaid() && $appointment->paymongo_checkout_id) {
            $this->confirmCheckout($appointment, $payMongo, $workflow);
        }
        $appointment->load(['lawyer.user', 'service', 'notarialDocument', 'attachments', 'notarialEntry']);

        return view('client.appointments.show', [
            'appointment' => $appointment,
            'paymentResult' => $request->query('payment'),
        ]);
    }

    public function pay(Appointment $appointment, PayMongo $payMongo): RedirectResponse
    {
        Gate::authorize('view', $appointment);
        abort_unless($appointment->canPayOnline(), 422, 'This appointment cannot be paid online.');

        $appointment->update(['payment_method' => 'online']);

        return BookingController::sendToCheckout($appointment, $payMongo);
    }

    /**
     * The client came back from PayMongo. Ask PayMongo directly instead of trusting the URL,
     * so payment is confirmed even before the webhook arrives (or when testing without one).
     */
    private function confirmCheckout(Appointment $appointment, PayMongo $payMongo, AppointmentWorkflow $workflow): void
    {
        try {
            $payment = $payMongo->paidPayment($appointment->paymongo_checkout_id);
        } catch (RequestException|ConnectionException) {
            return;
        }

        if ($payment) {
            $workflow->recordOnlinePayment($appointment, $payment['id'], $payment['method']);
            $appointment->refresh();
        }
    }

    public function cancel(Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);
        $workflow->cancel($appointment);

        return back()->with('status', "{$appointment->reference} was cancelled.");
    }
}
