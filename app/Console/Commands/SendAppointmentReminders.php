<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:send-reminders')]
#[Description('Send SMS and email reminders for approved appointments happening tomorrow')]
class SendAppointmentReminders extends Command
{
    public function handle(AppointmentWorkflow $workflow): int
    {
        $appointments = Appointment::query()
            ->status(AppointmentStatus::Approved)
            ->whereDate('scheduled_date', today()->addDay())
            ->whereNull('reminded_at')
            ->with(['lawyer.user', 'service', 'notarialDocument'])
            ->get();

        foreach ($appointments as $appointment) {
            $workflow->notify($appointment, 'reminder');
            $appointment->update(['reminded_at' => now()]);
        }

        $this->info("Sent {$appointments->count()} reminder(s).");

        return self::SUCCESS;
    }
}
