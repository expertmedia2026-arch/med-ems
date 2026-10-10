<?php

namespace App\Services;

use App\Models\DoctorAvailability;
use App\Models\User;

class DoctorAvailabilityService
{
    public function forDoctor(User $doctor)
    {
        return $doctor->doctorAvailabilities()->whereDate('date', '>=', today())->orderBy('date')->get();
    }

    public function add(User $doctor, string $date): DoctorAvailability
    {
        return $doctor->doctorAvailabilities()->firstOrCreate(['date' => $date]);
    }

    public function remove(User $doctor, DoctorAvailability $availability): void
    {
        abort_unless($availability->doctor_id === $doctor->id, 404);
        $availability->delete();
    }
}
