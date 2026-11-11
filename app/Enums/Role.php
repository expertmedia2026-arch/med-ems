<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Nurse = 'nurse';
    case ChiefNurse = 'chief_nurse';
    case Doctor = 'doctor';
    case Lab = 'lab';
    case Pharmacist = 'pharmacist';
    case Accountant = 'accountant';
    case ChiefAccountant = 'chief_accountant';
    case ChiefPharmacist = 'chief_pharmacist';
    case Cashier = 'cashier';
    case MortuaryAttendant = 'mortuary_attendant';
    case Patient = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::Lab => 'Lab Scientist',
            self::ChiefAccountant => 'Chief Accountant',
            self::ChiefPharmacist => 'Chief Pharmacist',
            self::ChiefNurse => 'Chief Nurse',
            self::MortuaryAttendant => 'Mortuary Attendant',
            default => ucfirst($this->value),
        };
    }

    /** Roles that staff can be given (everything except Patient). */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role !== self::Patient));
    }

    public function isFinanceStaff(): bool
    {
        return in_array($this, [self::Accountant, self::ChiefAccountant], true);
    }

    public function isPharmacyStaff(): bool
    {
        return in_array($this, [self::Pharmacist, self::ChiefPharmacist, self::Cashier], true);
    }
}
