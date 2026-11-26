<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use App\Services\AuditLogger;
use App\Services\ConflictChecker;
use App\Services\NotarialRegister;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public const FILTERS = [
        'pending' => 'Pending',
        'today' => 'Today',
        'approved' => 'Approved',
        'completed' => 'Completed',
        'no_show' => 'No-show',
        'notarial' => 'Notarial',
        'all' => 'All',
    ];

    public function index(Request $request, ConflictChecker $conflicts): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'pending';
        $search = trim((string) $request->query('q'));

        $appointments = $this->filtered($filter)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%")))
            ->with(['lawyer.user', 'service', 'notarialDocument'])
            ->when($filter === 'completed' || $filter === 'all', fn (Builder $query) => $query->orderByDesc('scheduled_date')->orderByDesc('start_time'), fn (Builder $query) => $query->chronological())
            ->paginate(15)
            ->withQueryString();

        $selected = $request->filled('selected')
            ? Appointment::where('reference', $request->query('selected'))->with(['lawyer.user', 'service', 'notarialDocument', 'attachments', 'notarialEntry'])->first()
            : $appointments->first()?->load(['attachments', 'notarialEntry']);

        $counts = collect(self::FILTERS)->keys()->mapWithKeys(fn (string $key) => [$key => $this->filtered($key)->count()]);

        return view('staff.appointments.index', [
            'appointments' => $appointments,
            'selected' => $selected,
            'filter' => $filter,
            'search' => $search,
            'counts' => $counts,
            'findings' => $selected ? $conflicts->check($selected) : [],
            'clientCount' => $conflicts->clientCount(),
            'flagged' => $appointments->getCollection()->mapWithKeys(fn (Appointment $appointment) => [$appointment->id => $conflicts->check($appointment) !== []]),
        ]);
    }

    public function approve(Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        return $this->attempt($appointment, fn () => $workflow->approve($appointment), "Approved {$appointment->reference}. The client was notified.");
    }

    public function decline(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:200']])['reason'] ?? null;

        return $this->attempt($appointment, fn () => $workflow->decline($appointment, $reason), "Declined {$appointment->reference}. The client was notified.");
    }

    public function cancel(Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        return $this->attempt($appointment, fn () => $workflow->cancel($appointment), "Cancelled {$appointment->reference}. The slot is open again.");
    }

    public function complete(Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        return $this->attempt($appointment, fn () => $workflow->complete($appointment), "Marked {$appointment->reference} as completed.");
    }

    public function noShow(Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        return $this->attempt($appointment, fn () => $workflow->markNoShow($appointment), "Marked {$appointment->reference} as a no-show.");
    }

    public function recordPayment(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'gcash', 'maya'])],
            'official_receipt_no' => ['nullable', 'string', 'max:30'],
        ]);

        return $this->attempt($appointment, fn () => $workflow->recordPayment($appointment, $data['method'], $data['official_receipt_no'] ?? null), 'Payment recorded.');
    }

    public function updateChecklist(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->isNotarial() && $appointment->status === AppointmentStatus::Approved, 422);

        $data = $request->validate(['id_type' => ['nullable', Rule::in(config('lexsched.id_types'))]]);

        $appointment->update([
            'check_appeared' => $request->boolean('check_appeared'),
            'check_id_presented' => $request->boolean('check_id_presented'),
            'check_document_complete' => $request->boolean('check_document_complete'),
            'id_type' => $data['id_type'] ?? null,
        ]);

        AuditLogger::log('checklist', "Updated notarial checklist for {$appointment->reference}", $appointment);

        return $this->backTo($appointment)->with('status', 'Checklist saved.');
    }

    public function notarize(Appointment $appointment, NotarialRegister $register): RedirectResponse
    {
        try {
            $entry = $register->record($appointment);
        } catch (DomainException $exception) {
            return $this->backTo($appointment)->withErrors(['action' => $exception->getMessage()]);
        }

        return $this->backTo($appointment)->with('status', "Recorded as {$entry->certificateLine()}.");
    }

    private function attempt(Appointment $appointment, Closure $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            return $this->backTo($appointment)->withErrors(['action' => $exception->getMessage()]);
        }

        return $this->backTo($appointment)->with('status', $message);
    }

    /**
     * Returns to the list the secretary came from, keeping the filter and search, with this appointment open.
     */
    private function backTo(Appointment $appointment): RedirectResponse
    {
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);

        return redirect()->route('staff.appointments.index', [
            ...array_intersect_key($query, array_flip(['filter', 'q', 'page'])),
            'selected' => $appointment->reference,
        ]);
    }

    /**
     * @return Builder<Appointment>
     */
    private function filtered(string $filter): Builder
    {
        $query = Appointment::query();

        return match ($filter) {
            'pending' => $query->status(AppointmentStatus::Pending),
            'today' => $query->whereDate('scheduled_date', today())->status(AppointmentStatus::Pending, AppointmentStatus::Approved, AppointmentStatus::Completed, AppointmentStatus::NoShow),
            'approved' => $query->status(AppointmentStatus::Approved),
            'completed' => $query->status(AppointmentStatus::Completed),
            'no_show' => $query->status(AppointmentStatus::NoShow),
            'notarial' => $query->whereNotNull('notarial_document_id')->status(AppointmentStatus::Pending, AppointmentStatus::Approved, AppointmentStatus::Completed),
            default => $query,
        };
    }
}
