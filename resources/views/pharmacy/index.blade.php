<x-app-layout>
    <x-slot name="header">Pharmacy</x-slot>

    @include('pharmacy._tabs')

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3" x-data x-init="setTimeout(() => window.location.reload(), 30000)">
        <p class="flex items-center gap-2 text-sm font-medium text-teal-900"><span class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500"></span> Live pharmacy view · refreshes every 30 seconds</p>
        <p class="text-xs text-teal-800">Updated {{ now()->format('d M Y, g:i:s A') }}</p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Patients served today', $pharmacySummary['patients'], 'Distinct patients with medicine dispensed'],
            ['Items dispensed today', $pharmacySummary['items'], 'Completed prescription items'],
            ['Injections dispensed', $pharmacySummary['injections'], 'Injection-form medicines today'],
            ['Pharmacists active today', $pharmacySummary['pharmacists'], 'Staff with at least one dispense'],
        ] as [$label, $value, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($value) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="mb-3 font-semibold">Waiting to be dispensed ({{ $queue->count() }})</h2>
            <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Patient</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Waiting</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($queue as $visit)
                            @php
                                $total   = $visit->prescriptions->count();
                                $paid    = $visit->prescriptions->filter(fn ($rx) => $rx->is_paid)->count();
                                $allPaid = $paid === $total || $visit->is_emergency;
                                $canDispense = $paid > 0 || $visit->is_emergency;
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $visit->patient->full_name }}</span>
                                    <span class="block font-mono text-xs text-slate-400">{{ $visit->patient->hospital_number }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $total }} drug(s)
                                    <span class="block text-xs text-slate-400">{{ $visit->prescriptions->pluck('drug.name')->join(', ') }}</span>
                                    <span class="block text-xs {{ $allPaid ? 'text-green-700' : 'text-amber-700' }}">
                                        {{ $visit->is_emergency ? 'Emergency · dispense without upfront payment' : "{$paid} of {$total} paid" }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ ($visit->closed_at ?? $visit->checked_in_at)->diffForHumans(short: true) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('pharmacy.show', $visit) }}"
                                       class="rounded-lg px-3 py-1.5 text-xs font-medium {{ $canDispense ? 'bg-teal-700 text-white hover:bg-teal-800' : 'border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                                        {{ $canDispense ? 'Dispense' : 'View (unpaid)' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Nothing waiting.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (auth()->user()->hasRole('admin', 'chief_pharmacist'))
                <section class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 class="font-semibold text-slate-900">Pharmacist activity today</h2>
                            <p class="mt-1 text-xs text-slate-500">Unique patients attended and items dispensed by each pharmacy staff member.</p>
                        </div>
                        <a href="{{ route('pharmacy.audit') }}" class="text-sm font-semibold text-teal-700 hover:underline">Open full audit →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr><th class="px-4 py-3">Pharmacist</th><th class="px-4 py-3">Patients</th><th class="px-4 py-3">Items dispensed</th><th class="px-4 py-3">Injections</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($pharmacistActivity as $row)
                                    <tr>
                                        <td class="px-4 py-3"><span class="font-medium text-slate-800">{{ $row['user']->name }}</span><span class="block text-xs text-slate-500">{{ $row['user']->role->label() }}</span></td>
                                        <td class="px-4 py-3 font-semibold text-slate-900">{{ number_format($row['patients']) }}</td>
                                        <td class="px-4 py-3">{{ number_format($row['items']) }}</td>
                                        <td class="px-4 py-3">{{ number_format($row['injections']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No pharmacy staff are assigned to the Pharmacy department yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <h2 class="mb-3 mt-8 font-semibold">Dispensed today</h2>
            <div class="rounded-xl border bg-white shadow-sm">
                <ul class="divide-y text-sm">
                    @forelse ($dispensedToday as $rx)
                        <li class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="font-medium">{{ $rx->drug->display_name }} × {{ $rx->dispensable_quantity ?? $rx->quantity }}</p>
                                <p class="text-slate-500">{{ $rx->visit->patient->full_name }} · {{ $rx->drug->form ?: 'Medicine' }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                {{ $rx->dispensed_at->format('g:i A') }}
                                <span class="block">{{ $rx->dispenser?->name }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">Nothing dispensed yet today.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div>
            <div class="rounded-xl border border-amber-200 bg-white shadow-sm">
                <h3 class="border-b border-amber-200 bg-amber-50 px-5 py-3 font-semibold text-amber-900">Low stock</h3>
                <ul class="divide-y text-sm">
                    @forelse ($lowStock as $drug)
                        <li class="flex items-center justify-between px-5 py-3">
                            <span>{{ $drug->display_name }}</span>
                            <x-badge color="red">{{ $drug->available_quantity }} available</x-badge>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">All stocked up.</li>
                    @endforelse
                </ul>
                @if ($lowStock->isNotEmpty())
                    <a href="{{ route('pharmacy.inventory', ['low' => 1]) }}" class="block border-t px-5 py-3 text-sm text-teal-700 hover:underline">Restock →</a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
