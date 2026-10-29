<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\DoctorAvailability;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Manages doctor availability dates consumed by appointment-booking workflows.
 */
class DoctorAvailabilityService
{
    /** Return the doctor's availability dates from today onward. */
    public function forDoctor(User $doctor)
    {
        return $doctor->doctorAvailabilities()->whereDate('date', '>=', today())->orderBy('date')->get();
    }

    /** Return future booking dates only for an active account with the doctor role. */
    public function forBooking(User $doctor): Collection
    {
        abort_unless($doctor->role === Role::Doctor && $doctor->is_active, 404);

        return $doctor->doctorAvailabilities()->whereDate('date', '>=', today())->orderBy('date')->get();
    }

    /** Add the date to a doctor's availability, leaving an existing date unchanged. */
    public function add(User $doctor, string $date): DoctorAvailability
    {
        return $doctor->doctorAvailabilities()->firstOrCreate(['date' => $date]);
    }

    /** Remove an availability date only when it belongs to the supplied doctor. */
    public function remove(User $doctor, DoctorAvailability $availability): void
    {
        abort_unless($availability->doctor_id === $doctor->id, 404);
        $availability->delete();
    }
}
