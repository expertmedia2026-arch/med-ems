<x-app-layout>
    <x-slot name="header">Accounting & Finance</x-slot>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Financial performance</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">Collections overview</h2>
                <p class="mt-2 text-sm text-slate-500">Review payments, collection activity, and outstanding balances.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('finance.export.pdf', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg bg-teal-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">
                    <span aria-hidden="true">↓</span> Export PDF
                </a>
                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-slate-500">Selected reporting period</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $rangeLabel }}</p>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('finance.index') }}" x-data="{ period: @js($filters['period']) }" class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="font-semibold text-slate-900">Report filters</h3>
                <p class="mt-1 text-xs text-slate-500">Filter collections by time period, department, and who received payment.</p>
            </div>
            <a href="{{ route('finance.index') }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">Reset filters</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <label class="text-xs font-medium text-slate-600">Reporting period
                <select name="period" x-model="period" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    <option value="month">Monthly</option>
                    <option value="quarter">Quarterly</option>
                    <option value="year">Annually</option>
                    <option value="custom">Date range</option>
                </select>
            </label>

            <label class="text-xs font-medium text-slate-600">Year
                <select name="year" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    @for ($year = now()->year; $year >= now()->year - 6; $year--)
                        <option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }}</option>
                    @endfor
                </select>
            </label>

            <label x-show="period === 'month'" class="text-xs font-medium text-slate-600">Month
                <select name="month" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}" @selected($filters['month'] === $month)>{{ \Carbon\Carbon::create()->month($month)->format('F') }}</option>
                    @endforeach
                </select>
            </label>

            <label x-show="period === 'quarter'" class="text-xs font-medium text-slate-600">Quarter
                <select name="quarter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    @foreach ([1 => 'Quarter 1 · Jan–Mar', 2 => 'Quarter 2 · Apr–Jun', 3 => 'Quarter 3 · Jul–Sep', 4 => 'Quarter 4 · Oct–Dec'] as $quarter => $label)
                        <option value="{{ $quarter }}" @selected($filters['quarter'] === $quarter)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-medium text-slate-600">Department
                <select name="department_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-medium text-slate-600">Collected by role
                <select name="role" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    <option value="">All payment sources</option>
                    <option value="online" @selected($filters['role'] === 'online')>Patient portal</option>
                    @foreach ($roles as $role)
                        @continue($role === \App\Enums\Role::Patient)
                        <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </label>

            <div x-show="period === 'custom'" class="grid grid-cols-2 gap-3 sm:col-span-2 xl:col-span-3">
                <label class="text-xs font-medium text-slate-600">Start date
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                </label>
                <label class="text-xs font-medium text-slate-600">End date
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                </label>
            </div>
        </div>

        @if ($errors->any())
            <p class="mt-3 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif

        <div class="mt-5 flex justify-end">
            <button class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-900">Apply filters</button>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Collections', $collected, 'bg-teal-50 text-teal-700', 'Payments received in selected period'],
            ['Transactions', $transactions, 'bg-blue-50 text-blue-700', 'Successful recorded payments'],
            ['Average payment', $average, 'bg-cyan-50 text-cyan-700', 'Average per transaction'],
            ['Outstanding balance', $outstanding, 'bg-rose-50 text-rose-700', 'Current balance across invoices'],
        ] as [$label, $value, $tone, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                            @if ($label === 'Transactions')
                                {{ number_format($value) }}
                            @else
                                ₦{{ number_format($value, 2) }}
                            @endif
                        </p>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $tone }}" aria-hidden="true">
                        @if ($label === 'Collections')
                            ₦
                        @elseif ($label === 'Transactions')
                            ↗
                        @elseif ($label === 'Average payment')
                            ◉
                        @else
                            ◷
                        @endif
                    </span>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h3 class="font-semibold text-slate-900">Collection trend</h3>
                <p class="mt-1 text-xs text-slate-500">Payments recorded by date · ₦</p>
            </div>
            <span class="text-xs text-slate-500">{{ $rangeLabel }}</span>
        </div>
        @php $maxChartAmount = max(1, collect($chart)->max('amount') ?? 0); @endphp
        <div class="flex h-48 items-end gap-2 border-b border-slate-200 px-1 sm:gap-3">
            @foreach ($chart as $bucket)
                @php $barHeight = $bucket['amount'] > 0 ? max(3, ($bucket['amount'] / $maxChartAmount) * 100) : 0; @endphp
                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2" title="{{ $bucket['label'] }}: ₦{{ number_format($bucket['amount'], 2) }}">
                    <span class="max-w-full truncate text-[10px] text-slate-500">{{ $bucket['amount'] > 0 ? '₦'.number_format($bucket['amount'], 0) : '' }}</span>
                    <div class="w-full max-w-12 shrink-0 rounded-t-md bg-teal-700 transition hover:bg-teal-800" style="height: {{ $barHeight }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-2 sm:gap-3" aria-hidden="true">
            @foreach ($chart as $bucket)
                <span class="min-w-0 flex-1 truncate text-center text-[10px] text-slate-500">{{ $bucket['label'] }}</span>
            @endforeach
        </div>
        @if ($transactions === 0)
            <p class="mt-4 text-center text-sm text-slate-500">No payments match these filters for this period.</p>
        @endif
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5">
            <h3 class="font-semibold text-slate-900">Revenue by department</h3>
            <p class="mt-1 text-xs text-slate-500">Revenue received in the selected period and filters.</p>
        </div>
        @php $maxDepartmentAmount = max(1, collect($departmentBreakdown)->max('amount') ?? 0); @endphp
        <div class="space-y-4">
            @forelse ($departmentBreakdown as $row)
                @php $departmentWidth = ($row['amount'] / $maxDepartmentAmount) * 100; @endphp
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                        <span class="truncate font-medium text-slate-700">{{ $row['department'] }}</span>
                        <span class="whitespace-nowrap font-semibold text-slate-900">₦{{ number_format($row['amount'], 2) }}</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="{{ $row['department'] }} collections: ₦{{ number_format($row['amount'], 2) }}">
                        <div class="h-full rounded-full bg-teal-700" style="width: {{ $departmentWidth }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-12 text-center text-sm text-slate-500">No department collections match these filters.</p>
            @endforelse
        </div>
    </section>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h3 class="font-semibold text-slate-900">Payment activity</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $payments->total() }} payment records in the selected report.</p>
            </div>
            <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">{{ $rangeLabel }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Receipt / invoice</th>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Received by</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payment->created_at->format('d M Y') }}<span class="mt-0.5 block text-xs text-slate-400">{{ $payment->created_at->format('g:i A') }}</span></td>
                            <td class="px-4 py-3"><span class="font-mono text-xs font-semibold text-teal-800">{{ $payment->receipt_number }}</span><span class="mt-0.5 block font-mono text-[11px] text-slate-400">{{ $payment->invoice->invoice_number }}</span></td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $payment->invoice->patient->full_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $payment->invoice->visit?->department?->name ?? 'Registration / general' }}</td>
                            <td class="px-4 py-3"><span class="text-slate-800">{{ $payment->receiver?->name ?? 'Patient portal' }}</span><span class="mt-0.5 block text-xs text-slate-400">{{ $payment->receiver?->role?->label() ?? 'Online payment' }}</span></td>
                            <td class="px-4 py-3 capitalize text-slate-600">{{ $payment->method }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">₦{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">No payment activity found for this report.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $payments->links() }}</div>
        @endif
    </section>
</x-app-layout>
