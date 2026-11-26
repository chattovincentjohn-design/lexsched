<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\PesoWords;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Printable acknowledgment receipt for a paid appointment.
 */
class ReceiptController extends Controller
{
    public function __invoke(Appointment $appointment): View
    {
        Gate::authorize('view', $appointment);
        abort_unless($appointment->isPaid(), 404, 'This appointment has not been paid yet.');

        $appointment->load(['service', 'lawyer.user', 'notarialDocument', 'notarialEntry']);

        return view('receipt', [
            'appointment' => $appointment,
            'amountInWords' => PesoWords::format((float) $appointment->fee),
        ]);
    }
}
