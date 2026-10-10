<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DoctorDirectoryRequest;
use App\Http\Requests\StoreDoctorAvailabilityRequest;
use App\Http\Resources\Api\V1\ConsultationTypeResource;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Http\Resources\Api\V1\DoctorAvailabilityResource;
use App\Http\Resources\Api\V1\DoctorResource;
use App\Models\Department;
use App\Models\DoctorAvailability;
use App\Models\User;
use App\Services\Api\V1\ReferenceDataService;
use App\Services\DoctorAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferenceDataController extends Controller
{
    public function departments(ReferenceDataService $referenceData): AnonymousResourceCollection
    {
        return DepartmentResource::collection($referenceData->departments());
    }

    public function consultationTypes(Department $department, ReferenceDataService $referenceData): AnonymousResourceCollection
    {
        return ConsultationTypeResource::collection($referenceData->consultationTypes($department));
    }

    public function doctors(DoctorDirectoryRequest $request, ReferenceDataService $referenceData): AnonymousResourceCollection
    {
        return DoctorResource::collection($referenceData->doctors($request->validated('department_id')));
    }

    public function availability(User $doctor, ReferenceDataService $referenceData): AnonymousResourceCollection
    {
        return DoctorAvailabilityResource::collection($referenceData->availability($doctor));
    }

    public function myAvailability(Request $request, DoctorAvailabilityService $availability): AnonymousResourceCollection
    {
        return DoctorAvailabilityResource::collection($availability->forDoctor($request->user()));
    }

    public function addAvailability(StoreDoctorAvailabilityRequest $request, DoctorAvailabilityService $availability): DoctorAvailabilityResource
    {
        return new DoctorAvailabilityResource($availability->add($request->user(), $request->validated('date')));
    }

    public function removeAvailability(Request $request, DoctorAvailability $doctorAvailability, DoctorAvailabilityService $availability)
    {
        $availability->remove($request->user(), $doctorAvailability);

        return response()->noContent();
    }
}
