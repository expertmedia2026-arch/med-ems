<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinanceReportService
{
    public function indexData(array $input, bool $forExport = false): array
    {
        $filters = $this->normalizeFilters($input);
        [$start, $end] = $this->periodDates($filters);

        $paymentsQuery = Payment::query()
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()]);

        $this->applyFilters($paymentsQuery, $filters);

        $chartPayments = (clone $paymentsQuery)->get(['id', 'amount', 'created_at']);
        $departmentPayments = (clone $paymentsQuery)
            ->with(['invoice.visit.department'])
            ->get(['id', 'invoice_id', 'amount']);
        $collected = (float) $chartPayments->sum(fn (Payment $payment) => (float) $payment->amount);
        $outstandingQuery = Invoice::query()->whereIn('status', ['unpaid', 'partial']);

        if ($filters['department_id']) {
            $outstandingQuery->whereHas('visit', fn (Builder $query) => $query->where('department_id', $filters['department_id']));
        }

        return [
            'filters' => $filters,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'roles' => Role::staff(),
            'payments' => (clone $paymentsQuery)
                ->with(['invoice.patient', 'invoice.visit.department', 'receiver'])
                ->latest('created_at')
                ->when($forExport, fn (Builder $query) => $query->get(), fn (Builder $query) => $query->paginate(15)->withQueryString()),
            'collected' => $collected,
            'transactions' => $chartPayments->count(),
            'average' => $chartPayments->isEmpty() ? 0 : $collected / $chartPayments->count(),
            'outstanding' => (float) $outstandingQuery->sum('total') - (float) $outstandingQuery->sum('amount_paid'),
            'chart' => $this->chartBuckets($chartPayments, $start, $end, $filters['period']),
            'departmentBreakdown' => $this->departmentBreakdown($departmentPayments),
            'rangeLabel' => $start->format('d M Y').' – '.$end->format('d M Y'),
        ];
    }

    private function normalizeFilters(array $input): array
    {
        return [
            'period' => $input['period'] ?? 'month',
            'year' => (int) ($input['year'] ?? now()->year),
            'month' => (int) ($input['month'] ?? now()->month),
            'quarter' => (int) ($input['quarter'] ?? (int) ceil(now()->month / 3)),
            'department_id' => $input['department_id'] ?? null,
            'role' => $input['role'] ?? null,
            'start_date' => $input['start_date'] ?? now()->startOfMonth()->toDateString(),
            'end_date' => $input['end_date'] ?? now()->toDateString(),
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodDates(array $filters): array
    {
        return match ($filters['period']) {
            'year' => [
                CarbonImmutable::create($filters['year'], 1, 1)->startOfDay(),
                CarbonImmutable::create($filters['year'], 12, 31)->endOfDay(),
            ],
            'quarter' => $this->quarterDates($filters['year'], $filters['quarter']),
            'custom' => [
                CarbonImmutable::parse($filters['start_date'])->startOfDay(),
                CarbonImmutable::parse($filters['end_date'])->endOfDay(),
            ],
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

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['department_id']) {
            $query->whereHas('invoice.visit', fn (Builder $visit) => $visit->where('department_id', $filters['department_id']));
        }

        if ($filters['role'] === 'online') {
            $query->whereNull('received_by');
        } elseif ($filters['role']) {
            $query->whereHas('receiver', fn (Builder $receiver) => $receiver->where('role', $filters['role']));
        }
    }

    private function chartBuckets(Collection $payments, CarbonImmutable $start, CarbonImmutable $end, string $period): array
    {
        $daily = $period === 'custom' && $start->diffInDays($end) < 31;
        $weekly = $period === 'month';
        $totals = $payments->groupBy(function (Payment $payment) use ($daily, $weekly) {
            $date = CarbonImmutable::parse($payment->created_at);

            return $daily ? $date->toDateString()
                : ($weekly ? (string) (int) ceil($date->day / 7) : $date->format('Y-m'));
        })->map(fn (Collection $rows) => (float) $rows->sum(fn (Payment $payment) => (float) $payment->amount));

        $buckets = [];
        if ($weekly) {
            for ($week = 1; $week <= 5; $week++) {
                $buckets[] = ['key' => (string) $week, 'label' => 'Week '.$week];
            }
        } elseif ($daily) {
            for ($date = $start->startOfDay(); $date <= $end; $date = $date->addDay()) {
                $buckets[] = ['key' => $date->toDateString(), 'label' => $date->format('j M')];
            }
        } else {
            for ($month = $start->startOfMonth(); $month <= $end; $month = $month->addMonth()) {
                $buckets[] = ['key' => $month->format('Y-m'), 'label' => $month->format('M')];
            }
        }

        return array_map(fn (array $bucket) => $bucket + ['amount' => $totals->get($bucket['key'], 0)], $buckets);
    }

    private function departmentBreakdown(Collection $payments): array
    {
        return $payments
            ->groupBy(fn (Payment $payment) => $payment->invoice->visit?->department?->name ?? 'Registration / general')
            ->map(fn (Collection $rows) => (float) $rows->sum(fn (Payment $payment) => (float) $payment->amount))
            ->sortDesc()
            ->map(fn (float $amount, string $department) => compact('department', 'amount'))
            ->values()
            ->all();
    }
}
