<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(): View
    {
        $holidays = Holiday::query()->whereDate('date', '>=', today()->startOfYear())->orderBy('date')->get();

        return view('admin.holidays', [
            'holidays' => $holidays,
            'bookedCounts' => Appointment::query()
                ->whereNotNull('slot_key')
                ->whereIn('scheduled_date', $holidays->map(fn (Holiday $holiday) => $holiday->date->toDateString()))
                ->selectRaw('scheduled_date, count(*) as total')
                ->groupBy('scheduled_date')
                ->toBase()
                ->pluck('total', 'scheduled_date'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:100'],
        ], ['date.unique' => 'That date is already a holiday.']);

        $holiday = Holiday::create($data);
        AuditLogger::log('holiday_added', "Added holiday {$holiday->name} ({$holiday->date->format('M j, Y')})", $holiday);

        $affected = Appointment::query()->whereNotNull('slot_key')->whereDate('scheduled_date', $holiday->date)->count();
        $message = "{$holiday->name} added. Clients can no longer book {$holiday->date->format('F j')}.";

        if ($affected > 0) {
            $message .= " {$affected} existing ".str('appointment')->plural($affected).' on that day still need to be moved: see the day calendar.';
        }

        return back()->with('status', $message);
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();
        AuditLogger::log('holiday_removed', "Removed holiday {$holiday->name} ({$holiday->date->format('M j, Y')})");

        return back()->with('status', "{$holiday->name} removed. {$holiday->date->format('F j')} is open for booking again.");
    }
}
