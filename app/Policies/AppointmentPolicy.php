<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Secretary => true,
            UserRole::Lawyer => $user->lawyer?->id === $appointment->lawyer_id,
            UserRole::Client => $appointment->client_id === $user->id,
        };
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->role === UserRole::Client
            && $appointment->client_id === $user->id
            && $appointment->status->isCancellable()
            && $appointment->isUpcoming();
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Secretary => $appointment->status->isCancellable(),
            UserRole::Client => $appointment->client_id === $user->id && $appointment->clientRescheduleBlocker() === null,
            default => false,
        };
    }

    public function writeNotes(User $user, Appointment $appointment): bool
    {
        return $user->role === UserRole::Lawyer && $user->lawyer?->id === $appointment->lawyer_id;
    }
}
