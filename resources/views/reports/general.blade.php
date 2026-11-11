<x-app-layout>
    <x-slot name="header">General Hospital Report</x-slot>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Hospital-wide operations</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">General performance report</h2>
                <p class="mt-2 text-sm text-slate-500">Patients, clinical activity, diagnostics, admissions, staffing, pharmacy, and billing in one report.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('reports.general.export.pdf', request()->query()) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-900">↓ Export PDF</a>
                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-slate-500">{{ $selectedDepartment?->name ?? 'All departments' }}</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $rangeLabel }}</p>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('reports.general') }}" x-data="{ period: @js($filters['period']) }" class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-slate-900">Report filters</h3>
                <p class="mt-1 text-xs text-slate-500">Patient registrations stay hospital-wide; department filtering applies to department-owned activity.</p>
            </div>
            <a href="{{ route('reports.general') }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">Reset</a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <label class="text-xs font-medium text-slate-600">Reporting period
                <select name="period" x-model="period" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                    <option value="month">Monthly</option><option value="quarter">Quarterly</option><option value="year">Annually</option><option value="custom">Date range</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Year
                <select name="year" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                    @for ($year = now()->year; $year >= now()->year - 6; $year--)
                        <option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }}</option>
                    @endfor
                </select>
            </label>
            <label x-show="period === 'month'" class="text-xs font-medium text-slate-600">Month
                <select name="month" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}" @selected($filters['month'] === $month)>{{ \Carbon\Carbon::create()->month($month)->format('F') }}</option>
                    @endforeach
                </select>
            </label>
            <label x-show="period === 'quarter'" class="text-xs font-medium text-slate-600">Quarter
                <select name="quarter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                    @foreach ([1 => 'Q1 · Jan–Mar', 2 => 'Q2 · Apr–Jun', 3 => 'Q3 · Jul–Sep', 4 => 'Q4 · Oct–Dec'] as $quarter => $label)
                        <option value="{{ $quarter }}" @selected($filters['quarter'] === $quarter)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Department
                <select name="department_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <div x-show="period === 'custom'" class="grid grid-cols-2 gap-3 sm:col-span-2">
                <label class="text-xs font-medium text-slate-600">Start date<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-xs font-medium text-slate-600">End date<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            </div>
        </div>
        @if ($errors->any())<p class="mt-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif
        <div class="mt-5 flex justify-end"><button class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Apply filters</button></div>
    </form>

    @php
        $headlineCards = [
            ['New patients', $patientSummary['registered'], 'NHIS '.$patientSummary['nhis'].' · Non-NHIS '.$patientSummary['non_nhis']],
            ['Patient encounters', $clinicalSummary['visits'], $clinicalSummary['consultations'].' consultations · '.$clinicalSummary['external_lab'].' external lab'],
            ['Admissions', $admissionSummary['admitted'], $admissionSummary['active_at_end'].' active at period end'],
            ['Collections', '₦'.number_format($billingSummary['collected'], 2), $billingSummary['transactions'].' payment transactions'],
        ];
        $sections = [
            ['Appointments', $appointmentSummary, ['total' => 'Scheduled in period']],
            ['Clinical workflow', $clinicalSummary, ['visits' => 'Encounters', 'consultations' => 'Consultations', 'triaged' => 'Triaged', 'completed' => 'Completed']],
            ['Laboratory', $laboratorySummary, ['requested' => 'Requests', 'completed' => 'Completed', 'pending' => 'Pending']],
            ['Pharmacy', $pharmacySummary, ['prescribed' => 'Prescriptions', 'dispensed' => 'Dispensed', 'pending' => 'Pending', 'quantity' => 'Units dispensed']],
            ['Medication administration', $medicationSummary, ['total' => 'Recorded doses', 'administered' => 'Administered']],
            ['Admissions', $admissionSummary, ['admitted' => 'New admissions', 'discharged' => 'Discharged', 'active_at_end' => 'Active at period end']],
            ['Staffing', $staffSummary, ['active' => 'Active staff', 'scheduled_duties' => 'Rostered duties']],
        ];
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($headlineCards as [$label, $value, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ is_numeric($value) ? number_format($value) : $value }}</p><p class="mt-3 text-xs text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </div>

    <div class="mb-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($sections as [$title, $summary, $metrics])
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">{{ $title }}</h3>
                <dl class="mt-4 grid grid-cols-2 gap-3">
                    @foreach ($metrics as $key => $label)
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($summary[$key]) }}</dd></div>
                    @endforeach
                </dl>
                @if (! empty($summary['statuses']) || ! empty($summary['roles']))
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (($summary['statuses'] ?? $summary['roles']) as $status => $count)
                            <span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800">{{ str($status)->replace('_', ' ')->title() }} {{ $count }}</span>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h3 class="font-semibold text-slate-900">Billing summary</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['Invoices', $billingSummary['invoices']], ['Billed', '₦'.number_format($billingSummary['billed'], 2)], ['Paid on these invoices', '₦'.number_format($billingSummary['invoice_paid'], 2)], ['Outstanding', '₦'.number_format($billingSummary['outstanding'], 2)], ['Collections in period', '₦'.number_format($billingSummary['collected'], 2)]] as [$label, $value])
                <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $value }}</p></div>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($billingSummary['payment_methods'] as $method => $count)<span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800">{{ str($method)->title() }} {{ $count }}</span>@endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-semibold text-slate-900">Department performance</h3><p class="mt-1 text-xs text-slate-500">Operational activity and directly attributed collections.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Department</th><th class="px-4 py-3 text-right">Visits</th><th class="px-4 py-3 text-right">Admissions</th><th class="px-4 py-3 text-right">Active staff</th><th class="px-4 py-3 text-right">Collections</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($departmentPerformance as $row)<tr><td class="px-4 py-3 font-medium text-slate-800">{{ $row['department'] }}</td><td class="px-4 py-3 text-right">{{ number_format($row['visits']) }}</td><td class="px-4 py-3 text-right">{{ number_format($row['admissions']) }}</td><td class="px-4 py-3 text-right">{{ number_format($row['staff']) }}</td><td class="px-4 py-3 text-right font-semibold">₦{{ number_format($row['revenue'], 2) }}</td></tr>
                    @empty <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No department activity in this period.</td></tr> @endforelse
                </tbody>
            </table></div>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-900">Top diagnoses</h3><p class="mt-1 text-xs text-slate-500">Most frequently recorded diagnoses.</p>
            <ol class="mt-4 space-y-3">
                @forelse ($topDiagnoses as $diagnosis)<li class="flex items-start justify-between gap-3 text-sm"><span class="text-slate-700">{{ $diagnosis['diagnosis'] }}</span><span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">{{ $diagnosis['total'] }}</span></li>
                @empty <li class="py-8 text-center text-sm text-slate-500">No diagnoses recorded.</li> @endforelse
            </ol>
        </section>
    </div>
</x-app-layout>
