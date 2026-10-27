<x-app-layout>
    <x-slot name="header">Triage</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-teal-50 text-teal-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2.2-6 4.1 12 2.2-6H21" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Triage</h1>
                        <p class="mt-1 text-sm text-slate-500">Record observations and route patients to their doctor. Emergency visits are prioritized and do not wait for billing.</p>
                    </div>
                </div>
            </div>
            <p class="text-sm text-slate-500">{{ now()->format('l, d M Y') }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Awaiting vitals</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $waitingCount }}</p>
                    </div>
                    <span class="rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Department queue</span>
                </div>
                <p class="mt-3 text-xs text-slate-500">Patients checked in and ready for nursing assessment.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Waiting over 30 minutes</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight {{ $overdueCount ? 'text-rose-700' : 'text-slate-900' }}">{{ $overdueCount }}</p>
                    </div>
                    <span class="rounded-lg {{ $overdueCount ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }} px-2.5 py-1 text-xs font-semibold">Attention</span>
                </div>
                <p class="mt-3 text-xs text-slate-500">Use the oldest check-ins first to keep the queue moving.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Sent to doctor today</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $readyCount }}</p>
                    </div>
                    <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Vitals complete</span>
                </div>
                <p class="mt-3 text-xs text-slate-500">Patients whose latest observations are ready for review.</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 sm:p-5">
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-900">Patients awaiting vitals</h2>
                        <p class="mt-1 text-sm text-slate-500">Oldest check-ins appear first. Routine visits wait for payment; emergency patients proceed to triage and stabilization immediately.</p>
                    </div>
                    <form method="GET" action="{{ route('triage.index') }}" class="grid gap-2 sm:grid-cols-[minmax(14rem,1fr)_auto_auto]">
                        <label class="sr-only" for="q">Search patients</label>
                        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search name, hospital no. or phone"
                               class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        @if ($departments->isNotEmpty())
                            <label class="sr-only" for="department_id">Department</label>
                            <select id="department_id" name="department_id" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <option value="">All departments</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) $selectedDepartment === (string) $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Filter</button>
                    </form>
                </div>
                @if ($search || $selectedDepartment)
                    <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                        <span>Filtered queue</span>
                        <a href="{{ route('triage.index') }}" class="font-medium text-teal-700 hover:underline">Clear filters</a>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Patient</th>
                            <th class="px-5 py-3">Visit / priority</th>
                            <th class="px-5 py-3">Care team</th>
                            <th class="px-5 py-3">Checked in</th>
                            <th class="px-5 py-3">Payment</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($waiting as $visit)
                            @php
                                $waitMinutes = (int) $visit->checked_in_at->diffInMinutes(now());
                                $isOverdue = $waitMinutes >= 30;
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($visit->patient->photo_url)
                                            <img src="{{ $visit->patient->photo_url }}" alt="" class="h-10 w-10 rounded-full border border-slate-200 object-cover">
                                        @else
                                            <span class="grid h-10 w-10 place-items-center rounded-full bg-teal-50 text-sm font-semibold text-teal-800">{{ mb_substr($visit->patient->first_name, 0, 1).mb_substr($visit->patient->last_name, 0, 1) }}</span>
                                        @endif
                                        <span>
                                            <span class="block font-semibold text-slate-900">{{ $visit->patient->full_name }}</span>
                                            <span class="mt-0.5 block font-mono text-xs text-slate-500">{{ $visit->patient->hospital_number }}</span>
                                            <span class="mt-0.5 block text-xs text-slate-400">{{ ucfirst($visit->patient->gender) }} · {{ $visit->patient->age ?? 'Age not recorded' }}{{ $visit->patient->age !== null ? ' yrs' : '' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="min-w-48 px-5 py-4">
                                    <span class="font-medium text-slate-800">{{ $visit->is_emergency ? 'Accident & Emergency' : ($visit->department?->name ?? 'Outpatient') }}</span>
                                    @if ($visit->is_emergency)
                                        <span class="mt-1 block"><x-badge color="red">A&E · {{ $visit->triage_priority ? ucfirst($visit->triage_priority) : 'Needs triage' }}</x-badge></span>
                                    @endif
                                    <span class="mt-1 block max-w-64 truncate text-xs text-slate-500" title="{{ $visit->complaint }}">{{ $visit->complaint ?: 'No presenting complaint recorded' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="text-slate-700">{{ $visit->doctor?->name ?? 'Unassigned doctor' }}</span>
                                    <span class="mt-1 block text-xs text-slate-400">Assigned clinician</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="font-medium {{ $isOverdue ? 'text-rose-700' : 'text-slate-800' }}">{{ $visit->checked_in_at->diffForHumans(short: true) }}</span>
                                    <span class="mt-1 block text-xs {{ $isOverdue ? 'text-rose-600' : 'text-slate-400' }}">{{ $waitMinutes }} min wait</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if ($visit->is_emergency)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">Emergency · stabilize first</span>
                                    @elseif ($visit->consultation_paid)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Cleared</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Awaiting payment</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($visit->consultation_paid)
                                        <a href="{{ route('triage.show', $visit) }}" class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-800">
                                            Record vitals
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd"/></svg>
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">Send to billing desk</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-14 text-center">
                                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-500">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2.2-6 4.1 12 2.2-6H21"/></svg>
                                    </span>
                                    <p class="mt-3 font-semibold text-slate-800">{{ $search || $selectedDepartment ? 'No patients match these filters' : 'The vitals queue is clear' }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $search || $selectedDepartment ? 'Adjust your search or clear the filters to view the full queue.' : 'Newly checked-in OPD patients will appear here.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($waiting->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $waiting->links() }}</div>
            @endif
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-slate-900">Ready for doctor</h2>
                        <p class="mt-1 text-sm text-slate-500">Vitals captured today and queued for clinical review.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">{{ $readyCount }} today</span>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($done as $visit)
                    @php($vitals = $visit->vitals)
                    <div class="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-52">
                            <p class="font-semibold text-slate-900">{{ $visit->patient->full_name }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $visit->patient->hospital_number }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $visit->department?->name ?? 'Outpatient' }} · {{ $visit->updated_at->diffForHumans(short: true) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Recorded by {{ $vitals?->recorder?->name ?? 'Nurse' }}</p>
                        </div>
                        <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4 xl:max-w-2xl">
                            @foreach ([['BP', $vitals?->blood_pressure ? $vitals->blood_pressure.' mmHg' : '—'], ['Pulse', $vitals?->pulse ? $vitals->pulse.' bpm' : '—'], ['Temperature', $vitals?->temperature ? $vitals->temperature.' °C' : '—'], ['SpO₂', $vitals?->spo2 ? $vitals->spo2.'%' : '—']] as [$label, $value])
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ $label }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-between gap-3 xl:min-w-52 xl:justify-end">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($vitals?->alerts() ?? [] as $alert)
                                    <x-badge color="red">{{ $alert }}</x-badge>
                                @empty
                                    <span class="text-xs text-emerald-700">No flagged readings</span>
                                @endforelse
                            </div>
                            <a href="{{ route('triage.show', $visit) }}" class="shrink-0 text-sm font-medium text-teal-700 hover:underline">Review</a>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $search || $selectedDepartment ? 'No ready patients match these filters.' : 'No patients have been sent to the doctor today yet.' }}</p>
                @endforelse
            </div>
            @if ($done->hasPages())
                <div class="border-t border-slate-200 px-5 py-3">{{ $done->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
