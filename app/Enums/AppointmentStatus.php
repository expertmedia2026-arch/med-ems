<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow    = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::NoShow    => 'No Show',
            self::CheckedIn => 'Checked In',
            default         => ucfirst($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::CheckedIn => 'amber',
            self::Completed => 'green',
            self::Cancelled, self::NoShow => 'red',
        };
    }
}