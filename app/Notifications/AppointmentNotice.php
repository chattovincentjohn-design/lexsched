<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotice extends Notification
{
    /**
     * @param  'received'|'approved'|'declined'|'cancelled'|'rescheduled'|'reminder'  $event
     */
    public function __construct(public Appointment $appointment, public string $event) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->routeNotificationFor('mail', $this) ? 'mail' : null,
            $notifiable->routeNotificationFor('sms', $this) ? SmsChannel::class : null,
        ]));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;

        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Hi {$appointment->client_name},")
            ->line($this->headline())
            ->line("Reference: {$appointment->reference}")
            ->line("Service: {$appointment->service->name}".($appointment->notarialDocument ? " – {$appointment->notarialDocument->name}" : ''))
            ->line("Lawyer: {$appointment->lawyer->name}")
            ->line('Schedule: '.$appointment->scheduleLabel())
            ->line('Mode: '.$appointment->modeLabel());

        if ($appointment->decline_reason && $this->event === 'declined') {
            $mail->line("Reason: {$appointment->decline_reason}");
        }

        if ($appointment->notarialDocument && in_array($this->event, ['received', 'approved', 'reminder'], true)) {
            $mail->line('Please bring: '.implode('; ', $appointment->notarialDocument->requirements).'.');
        }

        return $mail->line(config('lexsched.office_name').' · '.config('lexsched.office_address'));
    }

    public function toSms(object $notifiable): string
    {
        $a = $this->appointment;
        $when = $a->scheduled_date->format('M j').' '.$a->timeLabel();

        return match ($this->event) {
            'received' => "LexSched: We received your request {$a->reference} for {$when} with {$a->lawyer->name}. Status: PENDING. We will text you once confirmed.",
            'approved' => "LexSched: Your appointment {$a->reference} on {$when} with {$a->lawyer->name} is CONFIRMED.".($a->mode === 'video' ? " Video link: {$a->videoLink()}" : ' Please arrive 10 minutes early.'),
            'declined' => "LexSched: Sorry, we could not accept request {$a->reference} for {$when}.".($a->decline_reason ? " Reason: {$a->decline_reason}." : '').' You may book another schedule.',
            'cancelled' => "LexSched: Appointment {$a->reference} on {$when} has been cancelled.",
            'rescheduled' => "LexSched: Appointment {$a->reference} is now on {$when} with {$a->lawyer->name}. Status: ".strtoupper($a->status->label()).'.',
            'reminder' => "LexSched reminder: You have an appointment tomorrow, {$when}, with {$a->lawyer->name}. Ref {$a->reference}.",
            default => "LexSched: Update on appointment {$a->reference}.",
        };
    }

    private function subject(): string
    {
        return match ($this->event) {
            'received' => "Booking request received – {$this->appointment->reference}",
            'approved' => "Appointment confirmed – {$this->appointment->reference}",
            'declined' => "Booking request declined – {$this->appointment->reference}",
            'cancelled' => "Appointment cancelled – {$this->appointment->reference}",
            'rescheduled' => "Appointment moved – {$this->appointment->reference}",
            'reminder' => "Reminder: your appointment tomorrow – {$this->appointment->reference}",
            default => "Appointment update – {$this->appointment->reference}",
        };
    }

    private function headline(): string
    {
        return match ($this->event) {
            'received' => 'We received your booking request. The office will review it and confirm within one business day.',
            'approved' => 'Your appointment is confirmed.',
            'declined' => 'We are sorry, but we could not accept your booking request.',
            'cancelled' => 'Your appointment has been cancelled.',
            'rescheduled' => 'Your appointment has been moved to a new schedule.',
            'reminder' => 'This is a reminder of your appointment tomorrow.',
            default => 'There is an update on your appointment.',
        };
    }
}
