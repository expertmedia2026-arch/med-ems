<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LabBookingOptionsRequest;
use App\Http\Requests\AppointmentCheckInRequest;
use App\Http\Requests\AppointmentIndexRequest;
use App\Http\Requests\PortalAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Http\Resources\Api\V1\LabBookingOptionsResource;
use App\Http\Resources\Api\V1\PatientAppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentBookingService;
use App\Services\VisitCheckInService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function labOptions(LabBookingOptionsRequest $request, AppointmentBookingService $booking): LabBookingOptionsResource
    {
        $date = Carbon::createFromFormat('Y-m-d', $request->validated('date'))->startOfDay();

        return new LabBookingOptionsResource($booking->labBookingOptions($date) + ['date' => $date->toDateString()]);
    }

    public function index(Request $request, AppointmentBookingService $booking): AnonymousResourceCollection
    {
        $patient = $this->patient($request);

        return PatientAppointmentResource::collection($booking->portalAppointments($patient));
    }

    public function staffIndex(AppointmentIndexRequest $request, AppointmentBookingService $booking): AnonymousResourceCollection
    {
        $date = Carbon::parse($request->validated('date') ?? today()->toDateString());

        return AppointmentResource::collection($booking->indexData($date, $request->user())['appointments']);
    }

    public function staffShow(Appointment $appointment, AppointmentBookingService $booking): AppointmentResource
    {
        return new AppointmentResource($booking->appointmentDetails($appointment)->loadMissing('doctor.department', 'labTests'));
    }

    public function staffStore(StoreAppointmentRequest $request, AppointmentBookingService $booking): AppointmentResource
    {
        $appointment = $booking->bookForStaff($request->validated(), $request->user()->id);

        return new AppointmentResource($booking->appointmentDetails($appointment));
    }

    public function staffCancel(Appointment $appointment, AppointmentBookingService $booking): AppointmentResource
    {
        $booking->cancel($appointment);

        return new AppointmentResource($booking->appointmentDetails($appointment->refresh()));
    }

    public function checkIn(AppointmentCheckInRequest $request, Appointment $appointment, AppointmentBookingService $booking, VisitCheckInService $checkIn): VisitResource
    {
        if ($appointment->isLab()) {
            $visit = $checkIn->fromLabAppointment($appointment, $request->user()->id);
        } else {
            $departmentId = $checkIn->doctorDepartmentId($appointment);
            abort_unless($departmentId, 422, 'Assign this doctor to a department before checking in the appointment.');
            $visit = $checkIn->fromDoctorAppointment($appointment, $request->validated('consultation_type_id'));
        }

        abort_unless($visit, 422, 'The appointment could not be checked in. Check for an active visit or unavailable lab tests.');

        return new VisitResource($visit->loadMissing(['patient', 'doctor', 'department', 'consultationType']));
    }

    public function store(PortalAppointmentRequest $request, AppointmentBookingService $booking): PatientAppointmentResource
    {
        $patient = $this->patient($request);
        $result = $booking->bookForPatient($patient, $request->validated(), $request->user()->id);

        if ($result['error']) {
            throw ValidationException::withMessages([$result['field'] => $result['error']]);
        }

        $appointment = $booking->appointmentDetails($result['appointment']);

        return new PatientAppointmentResource($appointment);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentBookingService $booking)
    {
        if (! $booking->cancelForPatient($this->patient($request), $appointment)) {
            throw ValidationException::withMessages(['appointment' => 'This appointment can no longer be cancelled.']);
        }

        return response()->noContent();
    }

    private function patient(Request $request): Patient
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'A patient account is required to manage appointments.');

        return $patient;
    }
}
