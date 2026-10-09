<?php

namespace App\Enums;

enum VisitStatus: string
{
    case WaitingTriage  = 'waiting_triage';
    case WaitingDoctor  = 'waiting_doctor';
    case InConsultation = 'in_consultation';
    case Completed      = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::WaitingTriage  => 'Waiting for Triage',
            self::WaitingDoctor  => 'Waiting for Doctor',
            self::InConsultation => 'In Consultation',
            self::Completed      => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WaitingTriage, self::WaitingDoctor => 'amber',
            self::InConsultation => 'blue',
            self::Completed => 'green',
        };
    }
}