<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * Assembles a patient's portal landing-page appointment and billing summary.
 *
 * Queries are constrained to the supplied patient's own invoices, appointments,
 * and visits; authorization to access that patient remains the caller's responsibility.
 */
class PortalDashboardService
{
    /**
     * Calculate outstanding invoices and find the patient's next appointment and last visit.
     *
     * Scheduled consultations from today onward and lab appointments from the current
     * time onward are eligible for the next-appointment result.
     *
     * @param  Patient  $patient  Patient whose portal dashboard is being assembled.
     * @return array{patient: Patient, outstanding: float, unpaidInvoices: int, nextAppointment: \App\Models\Appointment|null, lastVisit: \App\Models\Visit|null} Dashboard summary.
     */
    public function data(Patient $patient): array
    {
        $unpaid = Invoice::where('patient_id', $patient->id)->whereIn('status', ['unpaid', 'partial']);

        return [
            'patient' => $patient,
            'outstanding' => (float) (clone $unpaid)->sum(DB::raw('total - amount_paid')),
            'unpaidInvoices' => (clone $unpaid)->count(),
            'nextAppointment' => $patient->appointments()
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('type', 'consultation')->whereDate('scheduled_at', '>=', today()))
                    ->orWhere(fn ($q) => $q->where('type', 'lab')->where('scheduled_at', '>=', now())))
                ->with('doctor')
                ->orderBy('scheduled_at')
                ->first(),
            'lastVisit' => $patient->visits()
                ->where('status', VisitStatus::Completed->value)
                ->with('doctor')
                ->latest('closed_at')
                ->first(),
        ];
    }
}
