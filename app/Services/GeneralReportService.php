<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\StaffDuty;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GeneralReportService
{
    /** @return array<string, mixed> */
    public function indexData(array $input): array
    {
        $filters = $this->normalizeFilters($input);
        [$start, $end] = $this->periodDates($filters);
        $departmentId = $filters['department_id'];

        $visits = Visit::query()->whereBetween('checked_in_at', [$start, $end]);
        $appointments = Appointment::query()->whereBetween('scheduled_at', [$start, $end]);
        $labRequests = LabRequest::query()->whereBetween('created_at', [$start, $end]);
        $prescriptions = Prescription::query()->whereBetween('created_at', [$start, $end]);
        $administrations = MedicationAdministration::query()->whereBetween('created_at', [$start, $end]);
        $admissions = Admission::query()->whereBetween('admitted_at', [$start, $end]);
        $invoices = Invoice::query()->whereBetween('created_at', [$start, $end]);
        $payments = Payment::query()->whereBetween('created_at', [$start, $end]);

        if ($departmentId) {
            $visits->where('department_id', $departmentId);
            $appointments->whereHas('doctor', fn (Builder $query) => $query->where('department_id', $departmentId));
            $labRequests->whereHas('visit', fn (Builder $query) => $query->where('department_id', $departmentId));
            $prescriptions->whereHas('visit', fn (Builder $query) => $query->where('department_id', $departmentId));
            $administrations->whereHas('visit', fn (Builder $query) => $query->where('department_id', $departmentId));
            $admissions->where('department_id', $departmentId);
            $invoices->whereHas('visit', fn (Builder $query) => $query->where('department_id', $departmentId));
            $payments->whereHas('invoice.visit', fn (Builder $query) => $query->where('department_id', $departmentId));
        }

        $invoiceTotals = (clone $invoices)
            ->selectRaw('COALESCE(SUM(total), 0) as billed, COALESCE(SUM(amount_paid), 0) as paid')
            ->first();
        $paymentRows = (clone $payments)->with('invoice.visit')->get(['id', 'invoice_id', 'amount', 'method']);
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        return [
            'filters' => $filters,
            'departments' => $departments,
            'selectedDepartment' => $departmentId ? $departments->firstWhere('id', (int) $departmentId) : null,
            'rangeLabel' => $start->format('d M Y').' – '.$end->format('d M Y'),
            'patientSummary' => [
                'registered' => Patient::query()->whereBetween('created_at', [$start, $end])->count(),
                'nhis' => Patient::query()->whereBetween('created_at', [$start, $end])->where('is_nhis', true)->count(),
                'non_nhis' => Patient::query()->whereBetween('created_at', [$start, $end])->where('is_nhis', false)->count(),
                'total' => Patient::query()->count(),
            ],
            'appointmentSummary' => [
                'total' => (clone $appointments)->count(),
                'statuses' => $this->breakdown(clone $appointments, 'status'),
            ],
            'clinicalSummary' => [
                'visits' => (clone $visits)->count(),
                'consultations' => (clone $visits)->where('type', 'consultation')->count(),
                'external_lab' => (clone $visits)->where('type', 'lab_only')->count(),
                'triaged' => (clone $visits)->whereNotNull('triaged_at')->count(),
                'completed' => (clone $visits)->where('status', 'completed')->count(),
                'statuses' => $this->breakdown(clone $visits, 'status'),
            ],
            'laboratorySummary' => [
                'requested' => (clone $labRequests)->count(),
                'completed' => (clone $labRequests)->where('status', 'completed')->count(),
                'pending' => (clone $labRequests)->where('status', 'pending')->count(),
                'statuses' => $this->breakdown(clone $labRequests, 'status'),
            ],
            'pharmacySummary' => [
                'prescribed' => (clone $prescriptions)->count(),
                'dispensed' => (clone $prescriptions)->where('status', 'completed')->count(),
                'pending' => (clone $prescriptions)->where('status', 'pending')->count(),
                'quantity' => (int) (clone $prescriptions)->where('status', 'completed')->sum('dispensable_quantity'),
                'statuses' => $this->breakdown(clone $prescriptions, 'status'),
            ],
            'medicationSummary' => [
                'total' => (clone $administrations)->count(),
                'administered' => (clone $administrations)->where('status', 'administered')->count(),
                'statuses' => $this->breakdown(clone $administrations, 'status'),
            ],
            'admissionSummary' => [
                'admitted' => (clone $admissions)->count(),
                'discharged' => Admission::query()
                    ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
                    ->whereBetween('discharged_at', [$start, $end])
                    ->count(),
                'active_at_end' => Admission::query()
                    ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
                    ->where('admitted_at', '<=', $end)
                    ->where(fn (Builder $query) => $query->whereNull('discharged_at')->orWhere('discharged_at', '>', $end))
                    ->count(),
                'statuses' => $this->breakdown(clone $admissions, 'status'),
            ],
            'staffSummary' => $this->staffSummary($start, $end, $departmentId),
            'billingSummary' => [
                'invoices' => (clone $invoices)->count(),
                'billed' => (float) ($invoiceTotals?->billed ?? 0),
                'invoice_paid' => (float) ($invoiceTotals?->paid ?? 0),
                'outstanding' => (float) ($invoiceTotals?->billed ?? 0) - (float) ($invoiceTotals?->paid ?? 0),
                'collected' => (float) $paymentRows->sum(fn (Payment $payment) => (float) $payment->amount),
                'transactions' => $paymentRows->count(),
                'invoice_statuses' => $this->breakdown(clone $invoices, 'status'),
                'payment_methods' => $this->breakdown(clone $payments, 'method'),
            ],
            'departmentPerformance' => $this->departmentPerformance($departments, $start, $end, $paymentRows, $departmentId),
            'topDiagnoses' => $this->topDiagnoses(clone $visits),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $input): array
    {
        return [
            'period' => $input['period'] ?? 'month',
            'year' => (int) ($input['year'] ?? now()->year),
            'month' => (int) ($input['month'] ?? now()->month),
            'quarter' => (int) ($input['quarter'] ?? (int) ceil(now()->month / 3)),
            'department_id' => $input['department_id'] ?? null,
            'start_date' => $input['start_date'] ?? now()->startOfMonth()->toDateString(),
            'end_date' => $input['end_date'] ?? now()->toDateString(),
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodDates(array $filters): array
    {
        return match ($filters['period']) {
            'year' => [CarbonImmutable::create($filters['year'], 1, 1)->startOfDay(), CarbonImmutable::create($filters['year'], 12, 31)->endOfDay()],
            'quarter' => $this->quarterDates($filters['year'], $filters['quarter']),
            'custom' => [CarbonImmutable::parse($filters['start_date'])->startOfDay(), CarbonImmutable::parse($filters['end_date'])->endOfDay()],
            default => [
                CarbonImmutable::create($filters['year'], $filters['month'], 1)->startOfMonth()->startOfDay(),
                CarbonImmutable::create($filters['year'], $filters['month'], 1)->endOfMonth()->endOfDay(),
            ],
        };
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function quarterDates(int $year, int $quarter): array
    {
        $start = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();

        return [$start->startOfDay(), $start->addMonths(2)->endOfMonth()->endOfDay()];
    }

    /** @param Builder<Model> $query */
    private function breakdown(Builder $query, string $column): array
    {
        return $query->selectRaw($column.', COUNT(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->mapWithKeys(fn ($count, $value) => [(string) $value => (int) $count])
            ->all();
    }

    /** @return array<string, mixed> */
    private function staffSummary(CarbonImmutable $start, CarbonImmutable $end, mixed $departmentId): array
    {
        $staff = User::query()->where('is_active', true)->where('role', '!=', Role::Patient->value)
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId));
        $duties = StaffDuty::query()->whereBetween('duty_date', [$start->toDateString(), $end->toDateString()])
            ->when($departmentId, fn (Builder $query) => $query->whereHas('staffMember', fn (Builder $staff) => $staff->where('department_id', $departmentId)));

        return [
            'active' => (clone $staff)->count(),
            'scheduled_duties' => $duties->count(),
            'roles' => $this->breakdown(clone $staff, 'role'),
        ];
    }

    /** @return array<int, array<string, int|float|string>> */
    private function departmentPerformance(Collection $departments, CarbonImmutable $start, CarbonImmutable $end, Collection $payments, mixed $departmentId): array
    {
        $visits = Visit::query()->whereBetween('checked_in_at', [$start, $end])->selectRaw('department_id, COUNT(*) as aggregate')->groupBy('department_id')->pluck('aggregate', 'department_id');
        $admissions = Admission::query()->whereBetween('admitted_at', [$start, $end])->selectRaw('department_id, COUNT(*) as aggregate')->groupBy('department_id')->pluck('aggregate', 'department_id');
        $staff = User::query()->where('is_active', true)->where('role', '!=', Role::Patient->value)->selectRaw('department_id, COUNT(*) as aggregate')->groupBy('department_id')->pluck('aggregate', 'department_id');
        $revenue = $payments->groupBy(fn (Payment $payment) => $payment->invoice->visit?->department_id)
            ->map(fn (Collection $rows) => (float) $rows->sum(fn (Payment $payment) => (float) $payment->amount));

        return $departments
            ->when($departmentId, fn (Collection $rows) => $rows->where('id', (int) $departmentId))
            ->map(fn (Department $department) => [
                'department' => $department->name,
                'visits' => (int) ($visits[$department->id] ?? 0),
                'admissions' => (int) ($admissions[$department->id] ?? 0),
                'staff' => (int) ($staff[$department->id] ?? 0),
                'revenue' => (float) ($revenue[$department->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['visits'] > 0 || $row['admissions'] > 0 || $row['staff'] > 0 || $row['revenue'] > 0)
            ->sortByDesc('visits')
            ->values()
            ->all();
    }

    /** @return array<int, array{diagnosis: string, total: int}> */
    private function topDiagnoses(Builder $visits): array
    {
        return $visits->whereNotNull('diagnosis')->where('diagnosis', '!=', '')
            ->selectRaw('diagnosis, COUNT(*) as aggregate')
            ->groupBy('diagnosis')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn (Visit $visit) => ['diagnosis' => $visit->diagnosis, 'total' => (int) $visit->aggregate])
            ->all();
    }
}
