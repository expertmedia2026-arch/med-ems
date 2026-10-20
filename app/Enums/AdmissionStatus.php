<?php

namespace App\Enums;

enum AdmissionStatus: string
{
    case Active = 'active';
    case Discharged = 'discharged';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Inpatient',
            self::Discharged => 'Discharged',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'teal',
            self::Discharged => 'slate',
        };
    }
}
