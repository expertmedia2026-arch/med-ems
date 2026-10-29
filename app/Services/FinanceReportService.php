<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds payment summaries, chart series, and department breakdowns for finance screens and exports.
 *
 * Payment, invoice, and department queries use the application's normal model scopes; this service
 * applies the optional report filters but does not perform authorization itself.
 */
class FinanceReportService
{
    /**
     * Assemble the selected period's payment totals, outstanding balance, charts, and payment rows.
     *
     * The input accepts period, date, department, and receiver-role filters. Export mode returns
     * all matching payments, while screen mode returns a query-string-preserving paginator.
     *
     * @param  array<string, mixed>  $input  Report filters such as period, year, dates, department, and role.
     * @return array{
     *     filters: array<string, mixed>,
     *     departments: \Illuminate\Database\Eloquent\Collection<int, Department>,
     *     roles: array,
     *     payments: LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection<int, Payment>,
     *     collected: float,
     *     transactions: int,
     *     average: float|int,
     *     outstanding: float,
     *     chart: array,
     *     departmentBreakdown: array,
     *     rangeLabel: string
     * }
     */
    public function indexData(array $input, bool $forExport = false): array
    {
        $filters = $this->normalizeFilters($input);
        [$start, $end] = $this->periodDates($filters);

        $paymentsQuery = Payment::query()
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()]);

        $this->applyFilters($paymentsQuery, $filters);

        $chartPayments = (clone $paymentsQuery)->get(['id', 'amount', 'created_at']);
        $departmentPayments = (clone $paymentsQuery)
            ->with(['invoice.visit.department', 'invoice.mortuaryCase'])
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
                ->with(['invoice.patient', 'invoice.visit.department', 'invoice.mortuaryCase', 'receiver'])
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

    /**
     * Fill omitted report filters with the current month and the current calendar date.
     *
     * Values are normalized to integer years/months/quarters where needed; validation and allowed
     * filter values are handled by the calling request layer.
     *
     * @param  array<string, mixed>  $input
     * @return array{period: mixed, year: int, month: int, quarter: int, department_id: mixed, role: mixed, start_date: mixed, end_date: mixed}
     */
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

    /**
     * Resolve the inclusive date-time bounds for the requested period.
     *
     * Custom date strings are parsed as supplied; other period types are derived from year,
     * month, or quarter filter values.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
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

    /**
     * Calculate the first and last instants of a three-month reporting quarter.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function quarterDates(int $year, int $quarter): array
    {
        $start = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();

        return [$start->startOfDay(), $start->addMonths(2)->endOfMonth()->endOfDay()];
    }

    /**
     * Add the optional department and payment-receiver role conditions to a payment query.
     *
     * The special "online" role means payments without a recorded receiver; other role values
     * are matched against the receiver's staff role.
     *
     * @param  Builder<Payment>  $query
     * @param  array<string, mixed>  $filters
     */
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

    /**
     * Group payments into weekly, daily, or monthly chart buckets for the selected range.
     *
     * Monthly reports use up to five day-of-month week buckets; short custom ranges use one bucket
     * per date, and longer ranges use calendar months. Empty buckets are included with amount zero.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array{key: string, label: string, amount: float}>
     */
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

    /**
     * Sum payment amounts by mortuary case, visit department, or the general-registration fallback.
     *
     * @param  Collection<int, Payment>  $payments  Payments with invoice visit and mortuary relations loaded.
     * @return array<int, array{department: string, amount: float}>
     */
    private function departmentBreakdown(Collection $payments): array
    {
        return $payments
            ->groupBy(fn (Payment $payment) => $payment->invoice->mortuaryCase ? 'Mortuary' : ($payment->invoice->visit?->department?->name ?? 'Registration / general'))
            ->map(fn (Collection $rows) => (float) $rows->sum(fn (Payment $payment) => (float) $payment->amount))
            ->sortDesc()
            ->map(fn (float $amount, string $department) => compact('department', 'amount'))
            ->values()
            ->all();
    }
}
