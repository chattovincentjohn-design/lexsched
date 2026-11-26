<?php

namespace App\Http\Controllers\Lawyer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use App\Models\Lawyer;
use App\Services\AppointmentWorkflow;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public const BLOCK_REASONS = ['Court hearing', 'Mediation', 'Client meeting outside', 'On leave', 'Personal'];

    public function index(Request $request): View
    {
        $lawyer = $this->lawyer($request);
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek()
            : (today()->isWeekend() ? today()->next(Carbon::MONDAY) : today()->startOfWeek());
        $days = collect(range(0, 4))->map(fn (int $offset) => $weekStart->copy()->addDays($offset));

        $appointments = $lawyer->appointments()
            ->whereBetween('scheduled_date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->whereNotNull('slot_key')
            ->with(['service', 'notarialDocument'])
            ->get()
            ->keyBy(fn (Appointment $appointment) => $appointment->scheduled_date->toDateString().'|'.$appointment->start_time);

        $blocks = $lawyer->blockedSlots()
            ->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->get();

        $selected = $request->filled('selected')
            ? $lawyer->appointments()->where('reference', $request->query('selected'))->with(['service', 'notarialDocument', 'attachments'])->first()
            : null;

        return view('lawyer.schedule', [
            'lawyer' => $lawyer,
            'days' => $days,
            'weekStart' => $weekStart,
            'appointments' => $appointments,
            'blocks' => $blocks,
            'selected' => $selected,
            'reasons' => self::BLOCK_REASONS,
            'todayCount' => $lawyer->appointments()->whereDate('scheduled_date', today())->where('status', 'approved')->count(),
            'pendingCount' => $lawyer->appointments()->where('status', 'pending')->count(),
        ]);
    }

    public function block(Request $request): RedirectResponse
    {
        $lawyer = $this->lawyer($request);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['nullable', Rule::in(config('lexsched.slots'))],
            'reason' => ['required', Rule::in(self::BLOCK_REASONS)],
        ]);

        $taken = $lawyer->appointments()->whereDate('scheduled_date', $data['date'])->whereNotNull('slot_key')
            ->when($data['time'] ?? null, fn ($query, string $time) => $query->where('start_time', $time))
            ->exists();

        if ($taken) {
            return back()->withErrors(['block' => 'There is already an appointment at that time. Ask the secretary to move or cancel it first.']);
        }

        BlockedSlot::create(['lawyer_id' => $lawyer->id, 'date' => $data['date'], 'start_time' => $data['time'] ?? null, 'reason' => $data['reason']]);
        AuditLogger::log('blocked', "{$lawyer->name} blocked ".Carbon::parse($data['date'])->format('M j').(isset($data['time']) ? " {$data['time']}" : ' (whole day)')." – {$data['reason']}");

        return back()->with('status', 'Time blocked. Clients can no longer book it.');
    }

    public function unblock(Request $request, BlockedSlot $block): RedirectResponse
    {
        abort_unless($block->lawyer_id === $this->lawyer($request)->id, 403);
        $block->delete();

        return back()->with('status', 'Block removed.');
    }

    public function saveNotes(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('writeNotes', $appointment);
        $appointment->update($request->validate(['lawyer_notes' => ['nullable', 'string', 'max:5000']]));

        return back()->with('status', 'Notes saved.');
    }

    public function complete(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('writeNotes', $appointment);

        try {
            $workflow->complete($appointment);
        } catch (DomainException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('status', 'Consultation marked completed.');
    }

    private function lawyer(Request $request): Lawyer
    {
        return $request->user()->lawyer ?? abort(403, 'Your account has no lawyer profile yet. Ask the administrator to set it up.');
    }
}
