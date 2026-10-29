<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\AdmissionStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Admission;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\StaffDuty;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\HospitalContext;

/**
 * Builds role-specific dashboard cards and the staff-facing visit queue.
 */
class DashboardService
{
    /**
     * Select dashboard cards for the user's role and attach their permitted live visit queue.
     *
     * Adds personal shift and emergency indicators where applicable. Queue results are limited
     * to operational staff roles and scoped to the viewer's department/doctor assignment.
     *
     * @return array{cards: array, queue: Collection}
     */
    public function data(User $user): array
    {
        $cards = match ($user->role) {
            Role::Admin => $this->admin(),
            Role::Receptionist => $this->receptionist(),
            Role::Nurse => $this->nurse(),
            Role::ChiefNurse => $this->chiefNurse(),
            Role::Doctor => $this->doctor($user->id),
            Role::Lab => $this->lab(),
            Role::Pharmacist => $this->pharmacist(),
            Role::ChiefPharmacist => $this->pharmacist(),
            Role::Accountant => $this->accountant(),
            Role::ChiefAccountant => $this->chiefAccountant(),
            default => [],
        };

        if (! $user->hasRole(Role::Admin, Role::Patient)) {
            $weekStart = now()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->addDays(6);
            $nextShift = StaffDuty::query()->where('user_id', $user->id)
                ->whereDate('duty_date', '>=', today())
                ->orderBy('duty_date')->first();

            array_unshift($cards,
                $this->card('My shifts this week', StaffDuty::where('user_id', $user->id)->whereBetween('duty_date', [$weekStart->toDateString(), $weekEnd->toDateString()])->count(), 'teal', 'nursing.roster.index'),
                $this->card('My next shift', $nextShift ? $nextShift->duty_date->format('D, d M').' · '.($nextShift->shift === '24_hour' ? '24-hour duty' : ucfirst($nextShift->shift)) : 'No upcoming shift', 'teal', 'nursing.roster.index'),
            );
        }

        if ($user->canAccessEmergency()) {
            $cards[] = $this->card(
                'Active A&E visits',
                Visit::where('is_emergency', true)
                    ->whereIn('status', [VisitStatus::WaitingTriage->value, VisitStatus::WaitingDoctor->value, VisitStatus::InConsultation->value])
                    ->count(),
                'red',
                'emergency.index',
            );
        }

        $queue = $user->hasRole(Role::Admin, Role::Receptionist, Role::Nurse, Role::Doctor)
            ? Visit::with(['patient', 'doctor', 'department'])
                ->withStatus(VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation)
                ->where('is_brought_in_dead', false)
                ->when($user->hasRole(Role::Nurse) && $user->department_id && ! $user->isCentralVitalsNurse(), fn ($query) => $query->where('department_id', $user->department_id))
                ->when($user->hasRole(Role::Doctor), fn ($query) => $query->where('department_id', $user->department_id ?? 0)
                    ->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $user->id)))
                ->oldest('checked_in_at')
                ->limit(10)
                ->get()
            : collect();

        return compact('cards', 'queue');
    }

    /** Assemble operational, inpatient, pharmacy, and finance summary cards for administrators. */
    private function admin(): array
    {
        return [
            $this->card('Patients today', $this->patientsToday(), 'blue', 'patients.index'),
            $this->card('In queue', $this->inQueue(), 'amber'),
            $this->card('Pending lab requests', LabRequest::pending()->count(), 'amber', 'lab.index'),
            $this->card('Revenue today', $this->money($this->revenueToday()), 'green', 'billing.index'),
            $this->card('Awaiting dispensing', $this->awaitingDispensing(), 'blue', 'pharmacy.index'),
            $this->card('Pharmacy patients today', $this->pharmacyPatientsToday(), 'teal', 'pharmacy.index'),
            $this->card('Dispensed today', Prescription::completed()->whereDate('dispensed_at', today())->count(), 'green', 'pharmacy.index'),
            $this->card('Staff duty roster', StaffDuty::whereBetween('duty_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->count().' shifts this week', 'teal', 'nursing.roster.index'),
            $this->card('Active admissions', Admission::where('status', AdmissionStatus::Active->value)->count(), 'teal', 'admissions.index'),
            $this->card('Admission results to review', Admission::whereHas('visit.labRequests', fn ($query) => $query->where('status', 'completed')->whereNull('result_reviewed_at'))->count(), 'amber', 'admissions.index'),
            $this->card('Nursing visits to finish', Visit::where('status', VisitStatus::Completed->value)
                ->whereNull('discharged_at')
                ->whereHas('prescriptions', fn ($query) => $query->where('requires_in_facility_administration', true))
                ->count(), 'blue', 'nursing.administrations.index'),
            $this->card('Low-stock drugs', Drug::active()->lowStock()->count(), 'red', 'pharmacy.inventory', ['low' => 1]),
            $this->card('Unpaid invoices', Invoice::whereIn('status', ['unpaid', 'partial'])->count(), 'red', 'billing.index'),
            $this->card('Outstanding balance', $this->money($this->outstanding()), 'red', 'billing.index'),
        ];
    }

    /** Assemble registration, appointments, checkout, and collection cards for receptionists. */
    private function receptionist(): array
    {
        return [
            $this->card('Patients today', $this->patientsToday(), 'blue', 'patients.index'),
            $this->card('In queue', $this->inQueue(), 'amber'),
            $this->card('Appointments today', Appointment::today()->where('status', 'scheduled')->count(), 'blue', 'appointments.index'),
            $this->card('Checked out today', Visit::today()->withStatus(VisitStatus::Completed)->count(), 'green'),
            $this->card('Collected today', $this->money($this->revenueToday()), 'teal', 'billing.index'),
            $this->card('Unpaid invoices', Invoice::whereIn('status', ['unpaid', 'partial'])->count(), 'amber', 'billing.index'),
        ];
    }

    /** Build department-aware nursing cards for ward care, triage, and visit follow-up. */
    private function nurse(): array
    {
        $nurse = auth()->user();
        $departmentId = $nurse->department_id;
        $isCentralNurse = $nurse->isCentralVitalsNurse();
        return [
            $this->card('Nursing visits to finish', Visit::whereIn('status', [VisitStatus::Completed->value, VisitStatus::Inpatient->value])
                ->whereNull('discharged_at')
                ->where(function ($query) use ($departmentId) {
                    $query->where(fn ($outpatient) => $outpatient->where('status', VisitStatus::Completed->value)->where('department_id', $departmentId ?? 0))
                        ->orWhere(fn ($inpatient) => $inpatient->where('status', VisitStatus::Inpatient->value)
                            ->whereHas('admission', fn ($admission) => $admission->where('status', AdmissionStatus::Active->value)->where('department_id', $departmentId ?? 0)));
                })
                ->whereHas('prescriptions', fn ($query) => $query->where('requires_in_facility_administration', true))
                ->count(), 'blue', 'nursing.administrations.index'),
            $this->card('Current inpatients', Admission::where('status', AdmissionStatus::Active->value)->where('department_id', $departmentId ?? 0)->count(), 'teal', 'admissions.index'),
            $this->card('Waiting for vitals', Visit::withStatus(VisitStatus::WaitingTriage)->when($departmentId && ! $isCentralNurse, fn ($query) => $query->where('department_id', $departmentId))->count(), 'amber', 'triage.index'),
            $this->card('Sent to doctor today', Visit::today()->withStatus(VisitStatus::WaitingDoctor, VisitStatus::InConsultation, VisitStatus::Completed)->when($departmentId && ! $isCentralNurse, fn ($query) => $query->where('department_id', $departmentId))->count(), 'green', 'triage.index'),
            $this->card('OPD queue', Visit::withStatus(VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation)->when($departmentId && ! $isCentralNurse, fn ($query) => $query->where('department_id', $departmentId))->count(), 'blue'),
        ];
    }

    /** Summarize the current week's roster and today's shift coverage for the chief nurse. */
    private function chiefNurse(): array
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        return [
            $this->card('Staff roster this week', StaffDuty::whereBetween('duty_date', [$weekStart->toDateString(), $weekEnd->toDateString()])->count(), 'teal', 'nursing.roster.index'),
            $this->card('Morning shifts today', StaffDuty::whereDate('duty_date', today())->where('shift', 'morning')->count(), 'teal', 'nursing.roster.index'),
            $this->card('Night shifts today', StaffDuty::whereDate('duty_date', today())->where('shift', 'night')->count(), 'teal', 'nursing.roster.index'),
        ];
    }

    /** Build consultation, inpatient, and lab-request cards for the given doctor. */
    private function doctor(int $doctorId): array
    {
        $departmentId = auth()->user()->department_id;
        $admissionScope = fn ($query) => $query->where(fn ($scope) => $scope
            ->where('attending_doctor_id', $doctorId)
            ->when($departmentId, fn ($scope) => $scope->orWhere('department_id', $departmentId)));

        return [
            $this->card('My department inpatients', Admission::where('status', AdmissionStatus::Active->value)->where($admissionScope)->count(), 'teal', 'admissions.index'),
            $this->card('Admission results to review', Admission::whereHas('visit.labRequests', fn ($query) => $query->where('status', 'completed')->whereNull('result_reviewed_at'))->where($admissionScope)->count(), 'amber', 'admissions.index'),
            $this->card('Waiting for me', Visit::withStatus(VisitStatus::WaitingDoctor)
                ->where('department_id', auth()->user()->department_id ?? 0)
                ->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $doctorId))
                ->count(), 'amber', 'consultations.index'),
            $this->card('In consultation', Visit::withStatus(VisitStatus::InConsultation)->where('doctor_id', $doctorId)->where('department_id', auth()->user()->department_id ?? 0)->count(), 'blue', 'consultations.index'),
            $this->card('Seen today', Visit::withStatus(VisitStatus::Completed)->where('doctor_id', $doctorId)->whereDate('closed_at', today())->count(), 'green'),
            $this->card('Previous consultations', Visit::withStatus(VisitStatus::Completed)->where('doctor_id', $doctorId)->count(), 'blue', 'consultations.history.index'),
            $this->card('My pending lab requests', LabRequest::pending()->where('requested_by', $doctorId)->count(), 'amber', 'lab.index'),
        ];
    }

    /** Summarize pending/completed lab work, catalogue coverage, and department inventory for lab staff. */
    private function lab(): array
    {
        $departmentId = auth()->user()->department_id ?? 0;
        $departmentRequests = fn ($query) => $query->whereHas('test', fn ($test) => $test->where('department_id', $departmentId));

        return [
            $this->card('Pending requests', LabRequest::pending()->where($departmentRequests)->count(), 'amber', 'lab.index'),
            $this->card('Completed today', LabRequest::completed()->whereDate('completed_at', today())->where($departmentRequests)->count(), 'green', 'lab.index'),
            $this->card('Active services in catalog', LabTest::active()->where('department_id', $departmentId)->count(), 'blue', 'lab.tests.index'),
            $this->card('Low-stock department items', \App\Models\DepartmentInventoryItem::query()->where('department_id', $departmentId)->whereColumn('quantity_on_hand', '<=', 'reorder_level')->count(), 'red', 'lab.inventory.index'),
        ];
    }

    /** Build dispensing, patient-volume, and low-stock cards for pharmacy roles. */
    private function pharmacist(): array
    {
        return [
            $this->card('Awaiting dispensing', $this->awaitingDispensing(), 'amber', 'pharmacy.index'),
            $this->card('Dispensed today', Prescription::completed()->whereDate('dispensed_at', today())->count(), 'green', 'pharmacy.index'),
            $this->card('Patients served today', $this->pharmacyPatientsToday(), 'teal', 'pharmacy.index'),
            $this->card('Low-stock drugs', Drug::active()->lowStock()->count(), 'red', 'pharmacy.inventory', ['low' => 1]),
        ];
    }

    /** Summarize collections and receivables for an accountant. */
    private function accountant(): array
    {
        return [
            $this->card('Revenue today', $this->money($this->revenueToday()), 'green', 'billing.index'),
            $this->card('Unpaid invoices', Invoice::whereIn('status', ['unpaid', 'partial'])->count(), 'amber', 'billing.index'),
            $this->card('Outstanding balance', $this->money($this->outstanding()), 'red', 'billing.index'),
        ];
    }

    /** Summarize collections and receivables with links to the finance reporting area. */
    private function chiefAccountant(): array
    {
        return [
            $this->card('Revenue today', $this->money($this->revenueToday()), 'green', 'finance.index'),
            $this->card('Unpaid invoices', Invoice::whereIn('status', ['unpaid', 'partial'])->count(), 'amber', 'finance.index'),
            $this->card('Outstanding balance', $this->money($this->outstanding()), 'red', 'finance.index'),
        ];
    }

    /**
     * Format a dashboard card and optionally resolve its named route with query parameters.
     *
     * @return array{label: string, value: int|string, color: string, url: string|null}
     */
    private function card(string $label, int|string $value, string $color, ?string $route = null, array $params = []): array
    {
        return ['label' => $label, 'value' => $value, 'color' => $color, 'url' => $route ? route($route, $params) : null];
    }

    /** Count distinct patients with a visit recorded today. */
    private function patientsToday(): int
    {
        return Visit::today()->distinct('patient_id')->count('patient_id');
    }

    /** Count visits currently waiting for triage/doctor care or in consultation. */
    private function inQueue(): int
    {
        return Visit::withStatus(VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation)->count();
    }

    /** Count prescriptions still awaiting dispensing from completed and inpatient visits. */
    private function awaitingDispensing(): int
    {
        return Visit::withStatus(VisitStatus::Completed, VisitStatus::Inpatient)->whereHas('prescriptions', fn ($q) => $q->pending())->count();
    }

    /** Count distinct patients with completed prescriptions dispensed today in the current hospital. */
    private function pharmacyPatientsToday(): int
    {
        return (int) DB::table('prescriptions')
            ->join('visits', 'visits.id', '=', 'prescriptions.visit_id')
            ->where('prescriptions.hospital_id', app(HospitalContext::class)->id())
            ->whereColumn('prescriptions.hospital_id', 'visits.hospital_id')
            ->where('prescriptions.status', 'completed')
            ->whereDate('prescriptions.dispensed_at', today())
            ->distinct('visits.patient_id')
            ->count('visits.patient_id');
    }

    /** Sum payment receipts recorded today. */
    private function revenueToday(): float
    {
        return (float) Payment::whereDate('created_at', today())->sum('amount');
    }

    /** Sum the unpaid balance on invoices that are currently unpaid or partially paid. */
    private function outstanding(): float
    {
        return (float) Invoice::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid'));
    }

    /** Render a monetary amount in the application's naira display format. */
    private function money(float $amount): string
    {
        return '₦'.number_format($amount, 2);
    }
}
