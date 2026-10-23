<?php

namespace App\Enums;

enum AdmissionStatus: string
{
    case Active = 'active';
    case Discharged = 'discharged';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Inpatient',
            self::Discharged => 'Discharged',
            self::Deceased => 'Deceased in hospital',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'teal',
            self::Discharged => 'slate',
            self::Deceased => 'red',
        };
    }
}
