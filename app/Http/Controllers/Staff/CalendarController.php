<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use App\Models\Holiday;
use App\Models\Lawyer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * One day across every lawyer: who is booked, blocked or free at each hour.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $day = $request->filled('date') ? Carbon::parse($request->query('date')) : today();

        $appointments = Appointment::query()
            ->whereDate('scheduled_date', $day)
            ->whereNotNull('slot_key')
            ->with(['service', 'notarialDocument'])
            ->get();

        $lawyers = Lawyer::query()
            ->with('user')
            ->where(fn ($query) => $query->where('accepts_bookings', true)->orWhereIn('id', $appointments->pluck('lawyer_id')))
            ->get()
            ->sortBy(fn (Lawyer $lawyer) => $lawyer->user->name)
            ->values();

        return view('staff.calendar', [
            'day' => $day,
            'lawyers' => $lawyers,
            'appointments' => $appointments->keyBy(fn (Appointment $appointment) => "{$appointment->lawyer_id}|{$appointment->start_time}"),
            'blocks' => BlockedSlot::query()->whereDate('date', $day)->get(),
            'holiday' => Holiday::query()->whereDate('date', $day)->first(),
            'slots' => config('lexsched.slots'),
        ]);
    }
}
