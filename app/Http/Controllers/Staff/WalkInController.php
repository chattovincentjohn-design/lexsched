<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalkInRequest;
use App\Models\NotarialDocument;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WalkInController extends Controller
{
    public function create(): View
    {
        return view('staff.walk-in', [
            'services' => Service::query()->active()->orderBy('id')->get(),
            'documents' => NotarialDocument::query()->active()->orderBy('fee')->get(),
        ]);
    }

    public function store(StoreWalkInRequest $request, BookingService $booking): RedirectResponse
    {
        $appointment = $booking->book($request->validated(), 'walk_in');

        return redirect()
            ->route('staff.appointments.index', ['filter' => 'all', 'selected' => $appointment->reference])
            ->with('status', "{$appointment->reference} added for {$appointment->scheduleLabel()}.");
    }
}
