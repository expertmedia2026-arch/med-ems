<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentCheckInRequest;
use App\Http\Requests\WalkInCheckInRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\VisitCheckInService;

class VisitController extends Controller
{
    /** Walk-in: check the patient in straight from their profile. */
    public function walkIn(WalkInCheckInRequest $request, Patient $patient, VisitCheckInService $checkIn)
    {
        $visit = $checkIn->walkIn($patient, $request->validated());

        if (! $visit) {
            return back()->with('error', 'This patient already has an active visit.');
        }

        $consultationType = $visit->consultationType;

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient checked in for '.$consultationType->name.'. Send them to the billing desk to pay ₦'.number_format((float) $consultationType->price, 2).', then to triage.');
    }

    /** Appointment: check in and link the visit to the booking. */
    public function fromAppointment(AppointmentCheckInRequest $request, Appointment $appointment, VisitCheckInService $checkIn)
    {
        if (! $appointment->scheduled_at->isToday()) {
            return back()->with('error', 'This appointment can only be checked in on '.$appointment->scheduled_at->format('l, d F Y').'.');
        }

        // Lab booking: becomes a lab-only visit with the booked tests (no consultation)
        if ($appointment->isLab()) {
            $visit = $checkIn->fromLabAppointment($appointment, $request->user()->id);

            if (! $visit) {
                return back()->with('error', 'None of the booked tests are available any more. Cancel this booking and book again.');
            }

            return back()->with('success', "{$visit->patient->full_name} checked in for lab tests. Send them to the billing desk to pay for the tests.");
        }

        if (! $checkIn->doctorDepartmentId($appointment)) {
            return back()->with('error', 'Assign this doctor to a department before checking in the appointment.');
        }

        $data = $request->validated();
        $visit = $checkIn->fromDoctorAppointment($appointment, $data['consultation_type_id']);

        if (! $visit) {
            return back()->with('error', 'This patient already has an active visit.');
        }

        $consultationType = $visit->consultationType;

        return back()->with('success', "{$visit->patient->full_name} checked in for {$consultationType->name}. Send them to the billing desk to pay ₦".number_format((float) $consultationType->price, 2).', then to triage.');
    }
}
