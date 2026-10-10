<?php

namespace App\Services\Api\V1;

use App\Models\Department;
use App\Models\User;
use App\Services\AppointmentBookingService;
use App\Services\ConsultationTypeService;
use App\Services\DepartmentService;
use App\Services\DoctorAvailabilityService;
use Illuminate\Support\Collection;

class ReferenceDataService
{
    public function __construct(
        private readonly DepartmentService $departmentsService,
        private readonly ConsultationTypeService $consultationTypesService,
        private readonly AppointmentBookingService $appointments,
        private readonly DoctorAvailabilityService $doctorAvailability,
    ) {}

    public function departments(): Collection
    {
        return $this->departmentsService->allForSelection();
    }

    public function consultationTypes(Department $department): Collection
    {
        return $this->consultationTypesService->activeForDepartment($department);
    }

    public function doctors(?int $departmentId): Collection
    {
        return $this->appointments->doctorsForDepartment($departmentId);
    }

    public function availability(User $doctor): Collection
    {
        return $this->doctorAvailability->forBooking($doctor);
    }
}
