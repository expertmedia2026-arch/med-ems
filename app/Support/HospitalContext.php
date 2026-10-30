<?php

namespace App\Support;

/**
 * Holds the hospital selected for the current request or console operation.
 *
 * The provider registers this object with scoped lifetime so one request cannot
 * leak its tenant into another. A null ID deliberately means "no tenant has
 * been established"; tenant-owned model scopes then fail closed for HTTP reads.
 */
class HospitalContext
{
    private ?int $hospitalId = null;

    /** Return the active tenant ID without attempting to infer one. */
    public function id(): ?int
    {
        return $this->hospitalId;
    }

    /**
     * Select or clear the active tenant.
     *
     * Long-running console loops must clear or replace this value between
     * hospitals because the scoped binding lives for the command lifecycle.
     */
    public function set(?int $hospitalId): void
    {
        $this->hospitalId = $hospitalId;
    }
}
