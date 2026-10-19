<x-app-layout>
    <x-slot name="header">Previous Consultation</x-slot>

    @php
        $patient = $visit->patient;
        $vitals  = $visit->vitals;
    @endphp

    <a href="{{ $backUrl }}" class="mb-4 inline-block text-sm text-teal-700 hover:underline">← Back to consultation</a>

    {{-- Banner --}}
    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
                <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
                <p class="text-sm text-slate-500">
                    {{ ucfirst($patient->gender) }} · {{ $patient->age }} yrs
                    · Blood {{ $patient->blood_group ?: '?' }} · Genotype {{ $patient->genotype ?: '?' }}
                </p>
                <p class="mt-1 text-sm text-slate-500">Department: {{ $visit->department?->name ?? '—' }}</p>
            </div>
            <div class="text-right text-sm">
                <p class="font-medium">{{ $visit->checked_in_at->format('d M Y, g:i A') }}</p>
                <p class="text-slate-500">Seen by {{ $visit->doctor?->name ?? '—' }}</p>
                <x-badge :color="$visit->status->color()" class="mt-1">{{ $visit->status->label() }}</x-badge>
            </div>
        </div>

        @if ($patient->allergies)
            <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                <strong>Allergies:</strong> {{ $patient->allergies }}
            </p>
        @endif
    </div>

    <div class="max-w-4xl space-y-6">

        {{-- Vitals --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold">Vitals</h3>

            @if ($vitals)
                <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4 lg:grid-cols-7">
                    @foreach ([
                        'Temp'   => $vitals->temperature ? $vitals->temperature . '°C' : null,
                        'Pulse'  => $vitals->pulse ? $vitals->pulse . ' bpm' : null,
                        'BP'     => $vitals->blood_pressure,
                        'Resp'   => $vitals->respiratory_rate,
                        'SpO₂'   => $vitals->spo2 ? $vitals->spo2 . '%' : null,
                        'Weight' => $vitals->weight ? $vitals->weight . ' kg' : null,
                        'BMI'    => $vitals->bmi,
                    ] as $k => $val)
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <p class="text-xs text-slate-500">{{ $k }}</p>
                            <p class="font-semibold">{{ $val ?: '—' }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($vitals->alerts())
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach ($vitals->alerts() as $alert)
                            <x-badge color="red">{{ $alert }}</x-badge>
                        @endforeach
                    </div>
                @endif

                @if ($vitals->notes)
                    <p class="mt-3 text-sm text-slate-600">Nurse's notes: {{ $vitals->notes }}</p>
                @endif
            @else
                <p class="text-sm text-slate-500">No vitals were recorded for this visit.</p>
            @endif
        </div>

        {{-- Assessment --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold">Assessment</h3>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Complaint</dt><dd class="font-medium">{{ $visit->complaint ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Diagnosis</dt><dd class="font-medium">{{ $visit->diagnosis ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Clinical notes</dt><dd class="whitespace-pre-line">{{ $visit->clinical_notes ?: '—' }}</dd></div>
                @if ($visit->patient_visible_note)
                    <div><dt class="text-slate-500">Note shared with patient</dt><dd class="whitespace-pre-line">{{ $visit->patient_visible_note }}</dd></div>
                @endif
            </dl>
        </div>

        {{-- Lab --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold">Lab tests and results</h3>

            <!-- @forelse ($visit->labRequests as $req)
                <div class="border-t py-3 first:border-t-0 first:pt-0">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $req->test->name }}</span>
                        <x-badge :color="$req->status->value === 'pending' ? 'amber' : 'green'">{{ $req->status->label() }}</x-badge>
                    </div>

                    @if ($req->result)
                        <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ $req->result }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $req->processor?->name }}{{ $req->completed_at ? ' · ' . $req->completed_at->format('d M Y, g:i A') : '' }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-slate-500">No result recorded.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No lab tests were requested.</p>
            @endforelse -->

            @forelse ($visit->labRequests as $req)
                <div class="border-t py-3 first:border-t-0 first:pt-0">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ $req->test->name }}</span>
                        <x-badge :color="$req->status->value === 'pending' ? 'amber' : 'green'">{{ $req->status->label() }}</x-badge>
                    </div>

                    @if ($req->result || $req->attachments->isNotEmpty())
                        @if ($req->result)
                            <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ $req->result }}</p>
                        @endif

                        @include('lab._attachments', ['attachments' => $req->attachments, 'canManage' => false])

                        <p class="mt-2 text-xs text-slate-400">
                            {{ $req->processor?->name }}{{ $req->completed_at ? ' · ' . $req->completed_at->format('d M Y, g:i A') : '' }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-slate-500">No result recorded.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No lab tests were requested.</p>
            @endforelse
        </div>

        {{-- Prescriptions --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold">Prescriptions</h3>

            @if ($visit->prescriptions->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Drug</th>
                                <th class="pr-4">Dosage</th>
                                <th class="pr-4">Frequency</th>
                                <th class="pr-4">Duration</th>
                                <th class="pr-4">Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($visit->prescriptions as $rx)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">
                                        {{ $rx->drug->display_name }}
                                        @if ($rx->instructions)
                                            <span class="block text-xs font-normal text-slate-400">{{ $rx->instructions }}</span>
                                        @endif
                                    </td>
                                    <td class="pr-4">{{ $rx->dosage }}</td>
                                    <td class="pr-4">{{ $rx->frequency }}</td>
                                    <td class="pr-4">{{ $rx->duration }}</td>
                                    <td class="pr-4">{{ $rx->status->value === 'completed' ? ($rx->dispensable_quantity ?? $rx->quantity).' supplied / '.$rx->quantity : ($rx->dispensable_quantity ?? $rx->quantity).' reserved / '.$rx->quantity }}</td>
                                    <td>
                                        @if ($rx->status->value === 'completed')
                                            <x-badge color="green">Dispensed</x-badge>
                                            <span class="block text-xs text-slate-400">{{ $rx->dispensed_at?->format('d M, g:i A') }}</span>
                                        @else
                                            <x-badge color="amber">Not dispensed</x-badge>
                                        @endif
                                        @if ($rx->requires_in_facility_administration)
                                            <span class="mt-1 block text-xs text-blue-700">Nurse: {{ $rx->administered_count }}/{{ $rx->required_administration_count }} doses given</span>
                                            @if ($rx->administration_waived_at)
                                                <span class="block text-xs text-amber-700">Waived: {{ $rx->administration_waiver_reason }}</span>
                                            @endif
                                            @foreach ($rx->administrations as $administration)
                                                <span class="block text-xs text-slate-500">
                                                    {{ ucfirst(str_replace('_', ' ', $administration->status)) }}
                                                    @if ($administration->administered_at) · {{ $administration->administered_at->format('d M, g:i A') }} @endif
                                                    @if ($administration->nurse) · {{ $administration->nurse->name }} @endif
                                                    @if ($administration->reason) · {{ $administration->reason }} @endif
                                                </span>
                                            @endforeach
                                            @if (! $rx->administration_waived_at && $rx->administered_count < $rx->required_administration_count && (auth()->user()->hasRole('admin') || $visit->doctor_id === auth()->id()))
                                                <form method="POST" action="{{ route('nursing.administrations.waive', [$visit, $rx]) }}" class="mt-2 flex flex-wrap gap-2">
                                                    @csrf
                                                    <input name="reason" required maxlength="2000" placeholder="Clinical reason to waive" class="w-48 rounded border-slate-300 text-xs">
                                                    <button class="text-xs text-amber-700 hover:underline">Waive with reason</button>
                                                </form>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500">No drugs were prescribed.</p>
            @endif
        </div>

        <a href="{{ $backUrl }}" class="inline-block text-sm text-teal-700 hover:underline">← Back to consultation</a>
    </div>
</x-app-layout>
