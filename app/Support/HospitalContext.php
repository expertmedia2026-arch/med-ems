<?php

namespace App\Support;

class HospitalContext
{
    private ?int $hospitalId = null;

    public function id(): ?int
    {
        return $this->hospitalId;
    }

    public function set(?int $hospitalId): void
    {
        $this->hospitalId = $hospitalId;
    }
}
