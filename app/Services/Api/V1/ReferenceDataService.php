<?php

namespace App\Services\Api\V1;

use App\Models\Department;
use App\Models\User;
use App\Services\AppointmentBookingService;
use App\Services\ConsultationTypeService;
use App\Services\DepartmentService;
use App\Services\DoctorAvailabilityService;
use Illuminate\Support\Collection;

/**
 * Exposes selection and scheduling reference data for the versioned API.
 *
 * Data retrieval is delegated to the established department, consultation-type,
 * appointment, and availability services so their existing tenant and eligibility
 * rules remain authoritative.
 */
class ReferenceDataService
{
    /**
     * @param  DepartmentService  $departmentsService  Lists departments available for selection.
     * @param  ConsultationTypeService  $consultationTypesService  Resolves active department consultation types.
     * @param  AppointmentBookingService  $appointments  Provides eligible doctors for booking.
     * @param  DoctorAvailabilityService  $doctorAvailability  Provides bookable doctor availability.
     */
    public function __construct(
        private readonly DepartmentService $departmentsService,
        private readonly ConsultationTypeService $consultationTypesService,
        private readonly AppointmentBookingService $appointments,
        private readonly DoctorAvailabilityService $doctorAvailability,
    ) {}

    /**
     * Return departments available for API selection.
     *
     * @return Collection<int, \App\Models\Department> Selection-ready department records.
     */
    public function departments(): Collection
    {
        return $this->departmentsService->allForSelection();
    }

    /**
     * Return active consultation types for a department.
     *
     * @param  Department  $department  Department whose consultation types are requested.
     * @return Collection<int, \App\Models\ConsultationType> Active consultation types.
     */
    public function consultationTypes(Department $department): Collection
    {
        return $this->consultationTypesService->activeForDepartment($department);
    }

    /**
     * Return doctors eligible for appointment booking in a department.
     *
     * Passing null delegates the all-department behavior to the booking service.
     *
     * @param  int|null  $departmentId  Optional department filter.
     * @return Collection<int, User> Doctors returned by the booking eligibility rules.
     */
    public function doctors(?int $departmentId): Collection
    {
        return $this->appointments->doctorsForDepartment($departmentId);
    }

    /**
     * Return bookable availability for the specified doctor.
     *
     * @param  User  $doctor  Doctor whose availability is requested.
     * @return Collection Availability slots returned by the booking availability service.
     */
    public function availability(User $doctor): Collection
    {
        return $this->doctorAvailability->forBooking($doctor);
    }
}
