<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether an appointment in this status still occupies its time slot.
     */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::Pending, self::Approved, self::Completed, self::NoShow], true);
    }

    /**
     * Whether the client may still cancel the appointment.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}
