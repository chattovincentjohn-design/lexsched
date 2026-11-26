<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\NotarialDocument;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(StoreBookingRequest $request, BookingService $booking): RedirectResponse
    {
        $user = $request->user();

        $appointment = $booking->book([
            ...$request->safe()->except(['attachment', 'consent']),
            'client_id' => $user->id,
            'client_name' => $user->name,
            'client_phone' => $user->phone,
            'client_email' => $user->email,
        ], 'online', $request->file('attachment'));

        return redirect()->route('client.appointments.show', $appointment)->with('booked', true);
    }
}
