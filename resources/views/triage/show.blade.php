<x-app-layout>
    <x-slot name="header">Record Vitals</x-slot>

    @php
        $patient = $visit->patient;
        $vitals = $visit->vitals;
        $input = 'mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $fields = [
            ['temperature', 'Temperature', '°C', '0.1', '30', '45'],
            ['pulse', 'Pulse', 'bpm', '1', '20', '250'],
            ['bp_systolic', 'Blood pressure · top number', 'mmHg', '1', '50', '300'],
            ['bp_diastolic', 'Blood pressure · bottom number', 'mmHg', '1', '30', '200'],
            ['respiratory_rate', 'Respiratory rate', '/min', '1', '5', '80'],
            ['spo2', 'Oxygen saturation', '%', '1', '50', '100'],
            ['weight', 'Weight', 'kg', '0.1', '0.5', '400'],
            ['height', 'Height', 'cm', '0.1', '20', '250'],
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-5">
        <a href="{{ route('triage.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-teal-700">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L9.06 10l3.71 3.71a.75.75 0 1 1-1.06 1.06l-4.24-4.24a.75.75 0 0 1 0-1.06l4.24-4.24a.75.75 0 0 1 1.08 0Z" clip-rule="evenodd"/></svg>
            Back to Central Vitals
        </a>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-teal-900 px-5 py-5 text-white sm:px-7">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-4">
                        @if ($patient->photo_url)
                            <img src="{{ $patient->photo_url }}" alt="{{ $patient->full_name }}" class="h-16 w-16 rounded-full border-2 border-white/60 object-cover">
                        @else
                            <span class="grid h-16 w-16 place-items-center rounded-full border border-white/30 bg-white/10 text-lg font-semibold">{{ mb_substr($patient->first_name, 0, 1).mb_substr($patient->last_name, 0, 1) }}</span>
                        @endif
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-teal-100">{{ $visit->is_emergency ? 'Accident & Emergency' : 'OPD · '.($visit->department?->name ?? 'Outpatient') }}</p>
                            <h1 class="mt-1 text-xl font-semibold sm:text-2xl">{{ $patient->full_name }}</h1>
                            <p class="mt-1 font-mono text-sm text-teal-100">{{ $patient->hospital_number }}</p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-white/20 bg-white/10 px-4 py-3 text-sm">
                        <p class="text-xs text-teal-100">Assigned clinician</p>
                        <p class="mt-1 font-semibold">{{ $visit->doctor?->name ?? 'Awaiting assignment' }}</p>
                        <p class="mt-1 text-xs text-teal-100">Checked in {{ $visit->checked_in_at->format('g:i A') }}</p>
                    </div>
                </div>
            </div>

            @if ($visit->is_emergency)
                <div class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-sm text-rose-900 sm:px-7">
                    Emergency visit · record each observation as a new entry. Triage and stabilization proceed without waiting for payment.
                </div>
            @endif

            <div class="grid gap-px bg-slate-200 sm:grid-cols-3">
                <div class="bg-white px-5 py-3 sm:px-7">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Age · sex</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ $patient->age !== null ? $patient->age.' years' : 'Age not recorded' }} · {{ ucfirst($patient->gender) }}</p>
                </div>
                <div class="bg-white px-5 py-3 sm:px-7">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Waiting time</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ $visit->checked_in_at->diffForHumans(short: true) }}</p>
                </div>
                <div class="bg-white px-5 py-3 sm:px-7">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Presenting complaint</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ $visit->complaint ?: 'Not recorded' }}</p>
                </div>
            </div>

            @if ($patient->allergies)
                <div class="mx-5 mt-5 flex gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 sm:mx-7">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-11a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 7Zm0 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    <div><strong>Recorded allergies</strong><p class="mt-0.5">{{ $patient->allergies }}</p></div>
                </div>
            @endif

            @if ($vitals?->alerts())
                <div class="mx-5 mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 sm:mx-7">
                    <p class="text-sm font-semibold text-amber-900">Review flagged readings</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($vitals->alerts() as $alert)
                            <x-badge color="red">{{ $alert }}</x-badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @if ($visit->is_emergency && $visit->emergencyObservations->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-rose-100 bg-white shadow-sm">
                <div class="border-b border-rose-100 px-5 py-4 sm:px-7">
                    <h2 class="font-semibold text-slate-900">A&E observation history</h2>
                    <p class="mt-1 text-sm text-slate-500">Each saved assessment is retained with the staff member and time.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Recorded</th><th class="px-5 py-3">Vitals</th><th class="px-5 py-3">Notes</th><th class="px-5 py-3">Recorded by</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($visit->emergencyObservations as $observation)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3">{{ $observation->recorded_at->format('d M Y, g:i A') }}</td>
                                    <td class="px-5 py-3">{{ $observation->temperature ? $observation->temperature.'°C · ' : '' }}{{ $observation->pulse ? 'P '.$observation->pulse.' · ' : '' }}{{ $observation->blood_pressure ? 'BP '.$observation->blood_pressure.' · ' : '' }}{{ $observation->spo2 ? 'SpO₂ '.$observation->spo2.'%' : '' }}</td>
                                    <td class="px-5 py-3">{{ $observation->notes ?: '—' }}</td>
                                    <td class="px-5 py-3">{{ $observation->recorder?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @include('emergency._progress-notes', ['visit' => $visit])
        @include('emergency._items-used', ['visit' => $visit, 'emergencyStockItems' => $emergencyStockItems])

        <form method="POST" action="{{ route('triage.store', $visit) }}"
              x-data="{ w: @js(old('weight', $vitals?->weight)), h: @js(old('height', $vitals?->height)) }"
              class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @csrf
            <div class="border-b border-slate-200 px-5 py-4 sm:px-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-slate-900">Nursing assessment</h2>
                    <p class="mt-1 text-sm text-slate-500">Enter the observations taken during this visit. Temperature and pulse are required for routine visits; emergency readings can be saved when unavailable.</p>
                    </div>
                    <span class="hidden rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800 sm:inline-flex">Central Vitals</span>
                </div>
            </div>

            <div class="p-5 sm:p-7">
                <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($fields as [$name, $label, $unit, $step, $min, $max])
                        <div>
                            <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}@if (! $visit->is_emergency && in_array($name, ['temperature', 'pulse'])) <span class="text-rose-600">*</span>@endif</label>
                            <div class="relative mt-1">
                                <input id="{{ $name }}" name="{{ $name }}" type="number" step="{{ $step }}" min="{{ $min }}" max="{{ $max }}"
                                       value="{{ old($name, $vitals?->{$name}) }}"
                                       @if ($name === 'weight') x-model="w" @endif
                                       @if ($name === 'height') x-model="h" @endif
                                       @required(! $visit->is_emergency && in_array($name, ['temperature', 'pulse']))
                                       class="block w-full rounded-lg border-slate-300 pr-14 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $unit }}</span>
                            </div>
                            @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>

                @if ($visit->is_emergency && $visit->status->value === 'waiting_triage')
                    <div class="mt-5 max-w-sm">
                        <label for="triage_priority" class="block text-sm font-medium text-slate-700">Triage priority <span class="text-rose-600">*</span></label>
                        <select id="triage_priority" name="triage_priority" required class="{{ $input }}">
                            <option value="">Select priority…</option>
                            @foreach (['critical' => 'Critical', 'high' => 'High', 'moderate' => 'Moderate', 'low' => 'Low'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('triage_priority', $visit->triage_priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('triage_priority')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-600">Body mass index <span class="ml-1 text-xs text-slate-400">calculated from weight and height</span></p>
                    <p class="text-lg font-semibold text-slate-900"><span x-text="(w && h) ? (w / Math.pow(h / 100, 2)).toFixed(1) : '—'"></span><span class="ml-1 text-xs font-normal text-slate-500">kg/m²</span></p>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <div>
                        <label for="complaint" class="block text-sm font-medium text-slate-700">Presenting complaint</label>
                        <input id="complaint" name="complaint" type="text" maxlength="500" value="{{ old('complaint', $visit->complaint) }}"
                               placeholder="Brief reason for today's visit" class="{{ $input }}">
                        @error('complaint')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-slate-700">Nurse's notes <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="2" maxlength="500" placeholder="Additional observations for the care team" class="{{ $input }}">{{ old('notes', $vitals?->notes) }}</textarea>
                        @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:px-7">
                <p class="text-xs text-slate-500">Saving records the nurse and time, then places the patient in the doctor's queue.</p>
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('triage.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-white">Cancel</a>
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                        {{ $visit->is_emergency && $visit->status->value !== 'waiting_triage' ? 'Save new observations' : 'Save and send to doctor' }}
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
