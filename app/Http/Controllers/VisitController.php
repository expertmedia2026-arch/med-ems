<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentCheckInRequest;
use App\Http\Requests\WalkInCheckInRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\VisitCheckInService;

/**
 * Validates walk-in and appointment check-ins and delegates visit creation to VisitCheckInService.
 */
class VisitController extends Controller
{
    /**
     * Checks in a patient from validated walk-in details or returns an existing-active-visit warning.
     */
    public function walkIn(WalkInCheckInRequest $request, Patient $patient, VisitCheckInService $checkIn)
    {
        $visit = $checkIn->walkIn($patient, $request->validated());

        if (! $visit) {
            return back()->with('error', 'This patient already has an active visit or inpatient admission.');
        }

        $consultationType = $visit->consultationType;

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient checked in for '.$consultationType->name.'. Send them to the billing desk to pay ₦'.number_format((float) $consultationType->price, 2).', then to triage.');
    }

    /**
     * Checks in the selected appointment through the appropriate service route, validating its date and booking context.
     */
    public function fromAppointment(AppointmentCheckInRequest $request, Appointment $appointment, VisitCheckInService $checkIn)
    {
        if (! $appointment->scheduled_at->isToday()) {
            return back()->with('error', 'This appointment can only be checked in on '.$appointment->scheduled_at->format('l, d F Y').'.');
        }

        // Lab booking: becomes a lab-only visit with the booked tests (no consultation)
        if ($appointment->isLab()) {
            $visit = $checkIn->fromLabAppointment($appointment, $request->user()->id);

            if (! $visit) {
                return back()->with('error', 'This patient may already have an inpatient admission, or none of the booked tests are available. Review the patient record before continuing.');
            }

            return back()->with('success', "{$visit->patient->full_name} checked in for lab tests. Send them to the billing desk to pay for the tests.");
        }

        if (! $checkIn->doctorDepartmentId($appointment)) {
            return back()->with('error', 'Assign this doctor to a department before checking in the appointment.');
        }

        $data = $request->validated();
        $visit = $checkIn->fromDoctorAppointment($appointment, $data['consultation_type_id']);

        if (! $visit) {
            return back()->with('error', 'This patient already has an active visit or inpatient admission.');
        }

        $consultationType = $visit->consultationType;

        return back()->with('success', "{$visit->patient->full_name} checked in for {$consultationType->name}. Send them to the billing desk to pay ₦".number_format((float) $consultationType->price, 2).', then to triage.');
    }
}
