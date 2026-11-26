<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentNotice;
use DomainException;
use Illuminate\Support\Facades\Notification;

/**
 * Every status change goes through here so the rules, the audit log and the
 * client notifications stay in one place.
 */
class AppointmentWorkflow
{
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
