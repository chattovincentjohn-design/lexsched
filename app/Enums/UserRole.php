<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Secretary = 'secretary';
    case Lawyer = 'lawyer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Secretary => 'Secretary',
            self::Lawyer => 'Lawyer',
            self::Admin => 'Administrator',
        };
    }

    /**
     * The page a user lands on after signing in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Client => 'client.appointments.index',
            self::Secretary => 'staff.appointments.index',
            self::Lawyer => 'lawyer.schedule',
            self::Admin => 'admin.dashboard',
        };
    }
}
