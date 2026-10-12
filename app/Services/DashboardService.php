<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @return array{cards: array, queue: Collection} */
    public function data(User $user): array
    {
        $cards = match ($user->role) {
            Role::Admin => $this->admin(),
            Role::Receptionist => $this->receptionist(),
            Role::Nurse => $this->nurse(),
            Role::Doctor => $this->doctor($user->id),
            Role::Lab => $this->lab(),
            Role::Pharmacist => $this->pharmacist(),
            Role::Cashier => $this->cashier(),
            default => [],
        };

        $queue = $user->hasRole(Role::Admin, Role::Receptionist, Role::Nurse, Role::Doctor)
            ? Visit::with(['patient', 'doctor', 'department'])
                ->withStatus(VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation)
                ->when($user->hasRole(Role::Nurse), fn ($query) => $query->where('department_id', $user->department_id ?? 0))
                ->when($user->hasRole(Role::Doctor), fn ($query) => $query->where('department_id', $user->department_id ?? 0)
                    ->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $user->id)))
                ->oldest('checked_in_at')
                ->limit(10)
                ->get()
            : collect();

        return compact('cards', 'queue');
    }

    private function admin(): array
    {
        return [
            $this->card('Patients today', $this->patientsToday(), 'blue', 'patients.index'),
            $this->card('In queue', $this->inQueue(), 'amber'),
            $this->card('Pending lab requests', LabRequest::pending()->count(), 'amber', 'lab.index'),
            $this->card('Revenue today', $this->money($this->revenueToday()), 'green', 'billing.index'),
            $this->card('Awaiting dispensing', $this->awaitingDispensing(), 'blue', 'pharmacy.index'),
            $this->card('Nursing visits to finish', Visit::where('status', VisitStatus::Completed->value)
                ->whereNull('discharged_at')
                ->whereHas('prescriptions', fn ($query) => $query->where('requires_in_facility_administration', true))
                ->count(), 'blue', 'nursing.administrations.index'),
            $this->card('Low-stock drugs', Drug::active()->lowStock()->count(), 'red', 'pharmacy.inventory', ['low' => 1]),
            $this->card('Unpaid invoices', Invoice::whereIn('status', ['unpaid', 'partial'])->count(), 'red', 'billing.index'),
            $this->card('Outstanding balance', $this->money($this->outstanding()), 'red', 'billing.index'),
        ];
    }

    private function receptionist(): array
    {
        return [
            $this->card('Patients today', $this->patientsToday(), 'blue', 'patients.index'),
            $this->card('In queue', $this->inQueue(), 'amber'),
            $this->card('Appointments today', Appointment::today()->where('status', 'scheduled')->count(), 'blue', 'appointments.index'),
            $this->card('Checked out today', Visit::today()->withStatus(VisitStatus::Completed)->count(), 'green'),
        ];
    }

    private function nurse(): array
    {
        $departmentId = auth()->user()->department_id ?? 0;

        return [
            $this->card('Nursing visits to finish', Visit::where('status', VisitStatus::Completed->value)
                ->where('department_id', $departmentId)
                ->whereNull('discharged_at')
                ->whereHas('prescriptions', fn ($query) => $query->where('requires_in_facility_administration', true))
                ->count(), 'blue', 'nursing.administrations.index'),
            $this->card('Waiting for triage', Visit::withStatus(VisitStatus::WaitingTriage)->where('department_id', $departmentId)->count(), 'amber', 'triage.index'),
            $this->card('Sent to doctor today', Visit::today()->withStatus(VisitStatus::WaitingDoctor, VisitStatus::InConsultation, VisitStatus::Completed)->where('department_id', $departmentId)->count(), 'green', 'triage.index'),
            $this->card('Total in queue', Visit::withStatus(VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation)->where('department_id', $departmentId)->count(), 'blue'),
        ];
    }

    private function doctor(int $doctorId): array
    {
        return [
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

    private function lab(): array
    {
        return [
            $this->card('Pending requests', LabRequest::pending()->count(), 'amber', 'lab.index'),
            $this->card('Completed today', LabRequest::completed()->whereDate('completed_at', today())->count(), 'green', 'lab.index'),
            $this->card('Active tests in catalog', LabTest::active()->count(), 'blue', 'lab.tests.index'),
        ];
    }

    private function pharmacist(): array
    {
        return [
            $this->card('Awaiting dispensing', $this->awaitingDispensing(), 'amber', 'pharmacy.index'),
            $this->card('Dispensed today', Prescription::completed()->whereDate('dispensed_at', today())->count(), 'green', 'pharmacy.index'),
            $this->card('Low-stock drugs', Drug::active()->lowStock()->count(), 'red', 'pharmacy.inventory', ['low' => 1]),
        ];
    }

    private function cashier(): array
    {
        return [
            $this->card('Revenue today', $this->money($this->revenueToday()), 'green', 'billing.index'),
            $this->card('Unpaid invoices', Invoice::whereIn('status', ['unpaid', 'partial'])->count(), 'amber', 'billing.index'),
            $this->card('Outstanding balance', $this->money($this->outstanding()), 'red', 'billing.index'),
        ];
    }

    private function card(string $label, int|string $value, string $color, ?string $route = null, array $params = []): array
    {
        return ['label' => $label, 'value' => $value, 'color' => $color, 'url' => $route ? route($route, $params) : null];
    }

    private function patientsToday(): int
    {
        return Visit::today()->distinct('patient_id')->count('patient_id');
    }

    private function inQueue(): int
    {
        return Visit::withStatus(VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation)->count();
    }

    private function awaitingDispensing(): int
    {
        return Visit::withStatus(VisitStatus::Completed)->whereHas('prescriptions', fn ($q) => $q->pending())->count();
    }

    private function revenueToday(): float
    {
        return (float) Payment::whereDate('created_at', today())->sum('amount');
    }

    private function outstanding(): float
    {
        return (float) Invoice::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - amount_paid'));
    }

    private function money(float $amount): string
    {
        return '₦'.number_format($amount, 2);
    }
}
