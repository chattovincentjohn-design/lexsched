<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Appointment;
use App\Models\NotarialDocument;
use App\Models\Service;
use App\Services\BookingService;
use App\Services\PayMongo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(Request $request): View
    {
        return view('client.book', [
            'services' => Service::query()->active()->orderBy('id')->get(),
            'documents' => NotarialDocument::query()->active()->orderBy('fee')->get(),
            'selectedService' => $request->integer('service') ?: null,
        ]);
    }

    public function store(StoreBookingRequest $request, BookingService $booking, PayMongo $payMongo): RedirectResponse
    {
        $user = $request->user();

        $appointment = $booking->book([
            ...$request->safe()->except(['attachment', 'consent']),
            'client_id' => $user->id,
            'client_name' => $user->name,
            'client_phone' => $user->phone,
            'client_email' => $user->email,
        ], 'online', $request->file('attachment'));

        if ($appointment->payment_method === 'online') {
            return $this->sendToCheckout($appointment, $payMongo);
        }

        return redirect()->route('client.appointments.show', $appointment)->with('booked', true);
    }

    /**
     * Opens PayMongo's payment page. If PayMongo can't be reached, the booking still stands and the
     * client can pay later from the appointment page.
     */
    public static function sendToCheckout(Appointment $appointment, PayMongo $payMongo): RedirectResponse
    {
        try {
            $checkout = $payMongo->createCheckout($appointment);
        } catch (RequestException|ConnectionException $exception) {
            Log::error("PayMongo checkout failed for {$appointment->reference}: {$exception->getMessage()}");

            return redirect()->route('client.appointments.show', $appointment)
                ->with('booked', true)
                ->withErrors(['action' => 'Online payment is not available right now. Your booking is saved; you can pay from this page later or at the office.']);
        }

        $appointment->update(['paymongo_checkout_id' => $checkout['id']]);

        return redirect()->away($checkout['checkout_url']);
    }
}
