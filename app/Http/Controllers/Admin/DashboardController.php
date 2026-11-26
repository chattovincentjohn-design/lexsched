<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Lawyer;
use App\Models\NotarialEntry;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $monthStart = today()->startOfMonth();
        $past = Appointment::query()->whereDate('scheduled_date', '>=', $monthStart)->status(AppointmentStatus::Completed, AppointmentStatus::NoShow);
        $pastCount = (clone $past)->count();
        $noShows = (clone $past)->where('status', AppointmentStatus::NoShow->value)->count();

        $active = Appointment::query()->whereNotNull('slot_key');

        $byService = Service::query()->orderBy('id')->get()->map(fn (Service $service) => [
            'label' => $service->name,
            'value' => (clone $active)->where('service_id', $service->id)->count(),
        ])->sortByDesc('value')->values();

        $byStatus = collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $status) => [
            'status' => $status,
            'value' => Appointment::where('status', $status->value)->count(),
        ]);

        $months = collect(range(5, 0))->map(function (int $back) {
            $month = today()->startOfMonth()->subMonths($back);

            return [
                'label' => $month->format('M'),
                'value' => Appointment::query()->whereNotNull('slot_key')->whereBetween('scheduled_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->count(),
            ];
        });

        $capacity = count(config('lexsched.slots')) * 5;
        $nextWeek = [today()->toDateString(), today()->addDays(6)->toDateString()];

        $workload = Lawyer::query()->with('user')->get()->map(fn (Lawyer $lawyer) => [
            'lawyer' => $lawyer,
            'upcoming' => $lawyer->appointments()->status(AppointmentStatus::Pending, AppointmentStatus::Approved)->whereDate('scheduled_date', '>=', today())->count(),
            'completed' => $lawyer->appointments()->status(AppointmentStatus::Completed)->whereDate('scheduled_date', '>=', $monthStart)->count(),
            'no_show' => $lawyer->appointments()->status(AppointmentStatus::NoShow)->whereDate('scheduled_date', '>=', $monthStart)->count(),
            'fees' => (float) $lawyer->appointments()->whereNotNull('paid_at')->where('paid_at', '>=', $monthStart)->sum('fee'),
            'utilization' => (int) round($lawyer->appointments()->whereNotNull('slot_key')->whereBetween('scheduled_date', $nextWeek)->count() / $capacity * 100),
        ]);

        return view('admin.dashboard', [
            'todayCount' => Appointment::whereDate('scheduled_date', today())->whereNotNull('slot_key')->count(),
            'pendingCount' => Appointment::where('status', AppointmentStatus::Pending->value)->count(),
            'noShowRate' => $pastCount ? (int) round($noShows / $pastCount * 100) : 0,
            'noShows' => $noShows,
            'pastCount' => $pastCount,
            'feesThisMonth' => (float) Appointment::whereNotNull('paid_at')->where('paid_at', '>=', $monthStart)->sum('fee'),
            'notarizedThisMonth' => NotarialEntry::whereDate('notarized_on', '>=', $monthStart)->count(),
            'byService' => $byService,
            'byStatus' => $byStatus,
            'months' => $months,
            'workload' => $workload,
            'recentLogs' => AuditLog::with('user')->latest('created_at')->limit(8)->get(),
            'monthLabel' => Carbon::now()->format('F Y'),
        ]);
    }
}
