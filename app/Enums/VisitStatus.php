<?php

namespace App\Enums;

enum VisitStatus: string
{
    case WaitingTriage  = 'waiting_triage';
    case WaitingDoctor  = 'waiting_doctor';
    case InConsultation = 'in_consultation';
    case Inpatient      = 'inpatient';
    case Completed      = 'completed';
    case LabOnly        = 'lab_only';

    public function label(): string
    {
        return match ($this) {
            self::WaitingTriage  => 'Waiting for Triage',
            self::WaitingDoctor  => 'Waiting for Doctor',
            self::InConsultation => 'In Consultation',
            self::Inpatient      => 'Inpatient',
            self::Completed      => 'Completed',
            self::LabOnly        => 'Lab Only (Referral)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WaitingTriage, self::WaitingDoctor => 'amber',
            self::InConsultation, self::LabOnly      => 'blue',
            self::Inpatient                          => 'teal',
            self::Completed                          => 'green',
        };
    }
}
