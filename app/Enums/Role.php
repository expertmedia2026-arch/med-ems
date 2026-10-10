<?php

namespace App\Enums;

enum Role: string
{
    case Admin        = 'admin';
    case Receptionist = 'receptionist';
    case Nurse        = 'nurse';
    case Doctor       = 'doctor';
    case Lab          = 'lab';
    case Pharmacist   = 'pharmacist';
    case Cashier      = 'cashier';
    case Patient      = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::Lab => 'Lab Scientist',
            default   => ucfirst($this->value),
        };
    }

    /** Roles that staff can be given (everything except Patient). */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::Patient));
    }
}