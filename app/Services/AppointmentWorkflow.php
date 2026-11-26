<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Notifications\AppointmentNotice;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

/**
 * Every status change goes through here so the rules, the audit log and the
 * client notifications stay in one place.
 */
class AppointmentWorkflow
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * Moves an appointment to another date, time or lawyer. A client's change goes back to the
     * secretary for approval; a change made by staff keeps its current status.
     */
    public function reschedule(Appointment $appointment, Lawyer $lawyer, string $date, string $time, bool $byStaff): void
    {
        if (! $appointment->status->isCancellable()) {
            throw new DomainException('Only pending or approved appointments can be moved.');
        }

        if (! $byStaff && ($reason = $appointment->clientRescheduleBlocker())) {
            throw new DomainException($reason);
        }

        if ($appointment->lawyer_id === $lawyer->id && $appointment->scheduled_date->toDateString() === $date && $appointment->start_time === $time) {
            throw new DomainException('Choose a different date or time.');
        }

        $availability = $byStaff ? $this->availability->forWalkIns() : $this->availability;

        if (! $availability->lawyersFor($appointment->service)->contains('id', $lawyer->id)) {
            throw new DomainException('That lawyer does not handle this service.');
        }

        if (! $availability->isSlotFree($lawyer, $date, $time)) {
            throw new DomainException('That time is no longer available. Please choose another.');
        }

        $previous = $appointment->scheduleLabel();

        try {
            $appointment->update([
                'lawyer_id' => $lawyer->id,
                'scheduled_date' => $date,
                'start_time' => $time,
                'status' => $byStaff ? $appointment->status : AppointmentStatus::Pending,
                'reminded_at' => null,
                'reschedule_count' => $appointment->reschedule_count + ($byStaff ? 0 : 1),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Someone just booked that time. Please choose another.');
        }

        $appointment->load('lawyer.user');
        AuditLogger::log('rescheduled', "Moved {$appointment->reference} from {$previous} to {$appointment->scheduleLabel()} with {$appointment->lawyer->name}", $appointment);
        $this->notify($appointment, 'rescheduled');
    }

    public function approve(Appointment $appointment): void
    {
        $this->transition($appointment, [AppointmentStatus::Pending], AppointmentStatus::Approved);
        $this->notify($appointment, 'approved');
    }

    public function decline(Appointment $appointment, ?string $reason = null): void
    {
        $appointment->decline_reason = $reason;
        $this->transition($appointment, [AppointmentStatus::Pending], AppointmentStatus::Declined);
        $this->notify($appointment, 'declined');
    }

    public function cancel(Appointment $appointment): void
    {
        $this->transition($appointment, [AppointmentStatus::Pending, AppointmentStatus::Approved], AppointmentStatus::Cancelled);
        $this->notify($appointment, 'cancelled');
    }

    public function complete(Appointment $appointment): void
    {
        if ($appointment->isNotarial()) {
            throw new DomainException('Notarial appointments are completed by recording them in the notarial register.');
        }

        $this->transition($appointment, [AppointmentStatus::Approved], AppointmentStatus::Completed);
    }

    public function markNoShow(Appointment $appointment): void
    {
        if ($appointment->isUpcoming()) {
            throw new DomainException('You can only mark a no-show after the appointment time has passed.');
        }

        $this->transition($appointment, [AppointmentStatus::Approved], AppointmentStatus::NoShow);
    }

    public function recordPayment(Appointment $appointment, string $method, ?string $receiptNo): void
    {
        if ($appointment->isPaid()) {
            throw new DomainException('This appointment is already paid.');
        }

        if (in_array($appointment->status, [AppointmentStatus::Declined, AppointmentStatus::Cancelled], true)) {
            throw new DomainException('Payments cannot be recorded for declined or cancelled appointments.');
        }

        $appointment->update(['paid_at' => now(), 'payment_method' => $method, 'official_receipt_no' => $receiptNo]);
        AuditLogger::log('payment', 'Recorded ₱'.number_format((float) $appointment->fee, 2)." payment for {$appointment->reference}".($receiptNo ? " (OR {$receiptNo})" : ''), $appointment);
    }

    /**
     * Records a payment confirmed by PayMongo. Safe to call twice for the same payment
     * (the webhook and the client's return to the site can both arrive).
     */
    public function recordOnlinePayment(Appointment $appointment, string $paymentId, string $method): bool
    {
        if ($appointment->isPaid()) {
            return false;
        }

        $appointment->update([
            'paid_at' => now(),
            'payment_method' => $method,
            'payment_reference' => $paymentId,
            'paymongo_payment_id' => $paymentId,
        ]);

        $label = Appointment::PAYMENT_METHODS[$method] ?? $method;
        AuditLogger::log('payment', 'PayMongo confirmed ₱'.number_format((float) $appointment->fee, 2)." {$label} payment for {$appointment->reference} ({$paymentId})", $appointment);

        return true;
    }

    public function notify(Appointment $appointment, string $event): void
    {
        $routes = array_filter(['mail' => $appointment->client_email, 'sms' => $appointment->client_phone]);

        if ($routes !== []) {
            Notification::routes($routes)->notify(new AppointmentNotice($appointment, $event));
        }
    }

    /**
     * @param  list<AppointmentStatus>  $from
     */
    private function transition(Appointment $appointment, array $from, AppointmentStatus $to): void
    {
        if (! in_array($appointment->status, $from, true)) {
            throw new DomainException("A {$appointment->status->label()} appointment cannot be changed to {$to->label()}.");
        }

        $appointment->status = $to;
        $appointment->save();

        AuditLogger::log($to->value, "{$to->label()} {$appointment->reference} ({$appointment->client_name})", $appointment);
    }
}
