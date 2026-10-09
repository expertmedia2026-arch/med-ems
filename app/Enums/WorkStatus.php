<?php

namespace App\Enums;

enum WorkStatus: string
{
    case Pending   = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}