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

    public function label(): string
    {
        return match ($this) {
            self::Lab => 'Lab Scientist',
            default   => ucfirst($this->value),
        };
    }
}