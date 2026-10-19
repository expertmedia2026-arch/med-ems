<x-app-layout>
    <x-slot name="header">Pharmacy dispensing audit</x-slot>

    @include('pharmacy._tabs')

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Pharmacy oversight</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">Dispensing history &amp; audit</h2>
                <p class="mt-2 text-sm text-slate-500">Review each dispensed medicine, the patient, quantity, date, and staff member who completed it.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <label class="text-xs font-medium text-slate-600">Reporting period
                    <select name="period" class="mt-1 block rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        @foreach ($periods as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Apply</button>
            </form>
        </div>
        <p class="mt-4 text-xs text-slate-500">Showing: <span class="font-semibold text-slate-700">{{ $rangeLabel }}</span></p>
    </section>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Patients attended', $summary['patients'], 'Unique patients with a completed dispense'],
            ['Items dispensed', $summary['items'], 'Completed prescription items'],
            ['Injections dispensed', $summary['injections'], 'Injection-form medicines'],
            ['Pharmacists active', $summary['pharmacists'], 'Staff who dispensed at least one item'],
        ] as [$label, $value, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($value) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </div>

    <section class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h3 class="font-semibold text-slate-900">Patients attended by pharmacist</h3>
            <p class="mt-1 text-xs text-slate-500">Counts cover {{ strtolower($rangeLabel) }} and count each patient once per pharmacist.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Pharmacist</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Patients</th><th class="px-4 py-3">Items</th><th class="px-4 py-3">Injections</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($pharmacistActivity as $row)
                        <tr>
                            <td class="px-4 py-3"><span class="font-medium text-slate-800">{{ $row['user']->name }}</span><span class="block text-xs text-slate-500">{{ $row['user']->role->label() }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['user']->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ number_format($row['patients']) }}</td>
                            <td class="px-4 py-3">{{ number_format($row['items']) }}</td>
                            <td class="px-4 py-3">{{ number_format($row['injections']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No pharmacists are assigned to the Pharmacy department yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" x-data x-init="setTimeout(() => window.location.reload(), 30000)">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h3 class="font-semibold text-slate-900">Dispensed medicines &amp; injections</h3>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($dispensings->total()) }} records · includes the dispensing pharmacist for every recorded item.</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> Live · refreshes every 30 seconds</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Date / time</th><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Medicine / injection</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Pharmacist</th><th class="px-4 py-3">Prescriber</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($dispensings as $prescription)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $prescription->dispensed_at->format('d M Y') }}<span class="mt-0.5 block text-xs text-slate-400">{{ $prescription->dispensed_at->format('g:i A') }}</span></td>
                            <td class="px-4 py-3"><span class="font-medium text-slate-800">{{ $prescription->visit->patient->full_name }}</span><span class="block font-mono text-xs text-slate-400">{{ $prescription->visit->patient->hospital_number }}</span></td>
                            <td class="px-4 py-3"><span class="font-medium text-slate-800">{{ $prescription->drug->display_name }}</span><span class="block text-xs text-slate-500">{{ $prescription->drug->form ?: 'Medicine' }} · {{ $prescription->dosage }}, {{ $prescription->frequency }}</span></td>
                            <td class="px-4 py-3">{{ $prescription->dispensable_quantity ?? $prescription->quantity }}</td>
                            <td class="px-4 py-3"><span class="font-medium text-slate-800">{{ $prescription->dispenser?->name ?? 'Unattributed record' }}</span><span class="block text-xs text-slate-500">{{ $prescription->dispenser?->role?->label() ?? 'No staff record' }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ $prescription->visit->doctor?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No completed dispensing records in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($dispensings->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $dispensings->links() }}</div>@endif
    </section>
</x-app-layout>
