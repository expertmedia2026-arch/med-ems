<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
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
    public function fromAppointment(Request $request, Appointment $appointment, BillingService $billing)
    {
        abort_unless($appointment->status === AppointmentStatus::Scheduled, 422);

        // Lab booking: becomes a lab-only visit with the booked tests (no consultation)
        if ($appointment->isLab()) {
            $tests = $appointment->labTests()->where('is_active', true)->get();

            if ($tests->isEmpty()) {
                return back()->with('error', 'None of the booked tests are available any more. Cancel this booking and book again.');
            }

            DB::transaction(function () use ($appointment, $tests, $request, $billing) {
                $visit = $appointment->patient->visits()->create([
                    'appointment_id' => $appointment->id,
                    'type'           => 'lab_only',
                    'status'         => VisitStatus::LabOnly,
                    'complaint'      => $appointment->reason,
                ]);

                foreach ($tests as $test) {
                    $visit->labRequests()->create([
                        'lab_test_id'  => $test->id,
                        'requested_by' => $request->user()->id,
                    ]);
                }

                $appointment->update(['status' => AppointmentStatus::CheckedIn]);

                $billing->syncFor($visit);
            });

            return back()->with('success', "{$appointment->patient->full_name} checked in for lab tests. Send them to the cashier to pay for the tests.");
        }

        // Doctor booking
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