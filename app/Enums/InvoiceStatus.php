<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid  = 'unpaid';
    case Partial = 'partial';
    case Paid    = 'paid';

    public function label(): string
    {
        return ucfirst($this->value);
    }
    public function color(): string
    {
        return match ($this) {
            self::Unpaid  => 'red',
            self::Partial => 'amber',
            self::Paid    => 'green',
        };
    }
}