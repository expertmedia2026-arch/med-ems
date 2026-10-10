<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\DoctorAvailability;
use App\Models\User;
use Illuminate\Support\Collection;

class DoctorAvailabilityService
{
    public function forDoctor(User $doctor)
    {
        return $doctor->doctorAvailabilities()->whereDate('date', '>=', today())->orderBy('date')->get();
    }

    public function forBooking(User $doctor): Collection
    {
        abort_unless($doctor->role === Role::Doctor && $doctor->is_active, 404);

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
