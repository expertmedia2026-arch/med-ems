<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    /** Walk-in: check the patient in straight from their profile. */
    public function walkIn(Request $request, Patient $patient, BillingService $billing)
    {
        if ($patient->currentVisit()) {
            return back()->with('error', 'This patient already has an active visit.');
        }

        $data = $request->validate([
            'complaint' => ['nullable', 'string', 'max:500'],
            'doctor_id' => ['nullable', Rule::exists('users', 'id')->where('role', Role::Doctor->value)],
        ]);

        $visit = $patient->visits()->create($data);
        $billing->invoiceFor($visit);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Patient checked in. Send them to the cashier to pay the consultation fee (₦' . number_format(config('hms.consultation_fee')) . '), then to triage.');
    }

    /** Appointment: check in and link the visit to the booking. */
    public function fromAppointment(Appointment $appointment, BillingService $billing)
    {
        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);

        if ($appointment->patient->currentVisit()) {
            return back()->with('error', 'This patient already has an active visit.');
        }

        DB::transaction(function () use ($appointment, $billing) {
            $visit = $appointment->patient->visits()->create([
                'appointment_id' => $appointment->id,
                'doctor_id'      => $appointment->doctor_id,
                'complaint'      => $appointment->reason,
            ]);

            $appointment->update(['status' => AppointmentStatus::CheckedIn]);

            $billing->invoiceFor($visit);
        });

        return back()->with('success', "{$appointment->patient->full_name} checked in. Send them to the cashier to pay the consultation fee, then to triage.");
    }
}