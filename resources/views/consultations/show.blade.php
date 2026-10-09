<x-app-layout>
    <x-slot name="header">Consultation</x-slot>

    @php
        $patient = $visit->patient;
        $vitals  = $visit->vitals;
        $input   = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label   = 'block text-sm font-medium text-slate-700';
        $requested = $visit->labRequests->pluck('lab_test_id');
    @endphp

    {{-- Patient banner --}}
    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
                <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
                <p class="text-sm text-slate-500">
                    {{ ucfirst($patient->gender) }} · {{ $patient->age }} yrs
                    · Blood {{ $patient->blood_group ?: '?' }} · Genotype {{ $patient->genotype ?: '?' }}
                </p>
            </div>
            <x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge>
        </div>

        @if ($patient->allergies)
            <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                <strong>Allergies:</strong> {{ $patient->allergies }}
            </p>
        @endif

        @if ($vitals)
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4 lg:grid-cols-7">
                @foreach ([
                    'Temp' => $vitals->temperature ? $vitals->temperature . '°C' : null,
                    'Pulse' => $vitals->pulse ? $vitals->pulse . ' bpm' : null,
                    'BP' => $vitals->blood_pressure,
                    'Resp' => $vitals->respiratory_rate,
                    'SpO₂' => $vitals->spo2 ? $vitals->spo2 . '%' : null,
                    'Weight' => $vitals->weight ? $vitals->weight . ' kg' : null,
                    'BMI' => $vitals->bmi,
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
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Diagnosis and notes --}}
            <div class="rounded-xl border bg-white p-6 shadow-sm">
                <h3 class="mb-4 font-semibold">Assessment</h3>

                @if ($editable)
                    <form id="visit-form" method="POST" action="{{ route('consultations.update', $visit) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="{{ $label }}">Presenting complaint</label>
                            <input name="complaint" type="text" value="{{ old('complaint', $visit->complaint) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Diagnosis</label>
                            <input name="diagnosis" type="text" value="{{ old('diagnosis', $visit->diagnosis) }}" class="{{ $input }}">
                            @error('diagnosis')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Clinical notes</label>
                            <textarea name="clinical_notes" rows="5" class="{{ $input }}">{{ old('clinical_notes', $visit->clinical_notes) }}</textarea>
                        </div>
                    </form>
                @else
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-slate-500">Complaint</dt><dd class="font-medium">{{ $visit->complaint ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Diagnosis</dt><dd class="font-medium">{{ $visit->diagnosis ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Clinical notes</dt><dd class="whitespace-pre-line">{{ $visit->clinical_notes ?: '—' }}</dd></div>
                    </dl>
                @endif
            </div>

            {{-- Prescriptions --}}
            <div class="rounded-xl border bg-white p-6 shadow-sm">
                <h3 class="mb-4 font-semibold">Prescriptions</h3>

                @if ($visit->prescriptions->isNotEmpty())
                    <div class="mb-5 overflow-x-auto">
                        <table class="min-w-full divide-y text-sm">
                            <thead class="text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="py-2 pr-4">Drug</th><th class="pr-4">Dosage</th><th class="pr-4">Frequency</th>
                                    <th class="pr-4">Duration</th><th class="pr-4">Qty</th><th class="pr-4">Status</th><th></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($visit->prescriptions as $rx)
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">{{ $rx->drug->display_name }}</td>
                                        <td class="pr-4">{{ $rx->dosage }}</td>
                                        <td class="pr-4">{{ $rx->frequency }}</td>
                                        <td class="pr-4">{{ $rx->duration }}</td>
                                        <td class="pr-4">{{ $rx->quantity }}</td>
                                        <td class="pr-4"><x-badge :color="$rx->status->value === 'pending' ? 'amber' : 'green'">{{ $rx->status->value === 'pending' ? 'Pending' : 'Dispensed' }}</x-badge></td>
                                        <td class="text-right">
                                            @if ($editable && $rx->status->value === 'pending')
                                                <form method="POST" action="{{ route('consultations.prescriptions.destroy', [$visit, $rx]) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="text-xs text-red-600 hover:underline">Remove</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mb-4 text-sm text-slate-500">No drugs prescribed.</p>
                @endif

                @if ($editable)
                    <form method="POST" action="{{ route('consultations.prescriptions.store', $visit) }}"
                          class="grid gap-4 rounded-lg bg-slate-50 p-4 sm:grid-cols-2">
                        @csrf

                        <div class="sm:col-span-2">
                            <label class="{{ $label }}">Drug</label>
                            <select name="drug_id" required class="{{ $input }}">
                                <option value="">Select drug…</option>
                                @foreach ($drugs as $drug)
                                    <option value="{{ $drug->id }}" @selected(old('drug_id') == $drug->id)>
                                        {{ $drug->display_name }}: {{ $drug->stock_quantity }} in stock
                                    </option>
                                @endforeach
                            </select>
                            @error('drug_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="{{ $label }}">Dosage</label>
                            <input name="dosage" type="text" required placeholder="e.g. 2 tablets" value="{{ old('dosage') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Frequency</label>
                            <select name="frequency" required class="{{ $input }}">
                                @foreach (['Once daily', 'Twice daily', 'Three times daily', 'Four times daily', 'At night', 'When required'] as $f)
                                    <option @selected(old('frequency') === $f)>{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Duration</label>
                            <input name="duration" type="text" required placeholder="e.g. 5 days" value="{{ old('duration') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Quantity to dispense</label>
                            <input name="quantity" type="number" min="1" required value="{{ old('quantity') }}" class="{{ $input }}">
                            @error('quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}">Instructions (optional)</label>
                            <input name="instructions" type="text" placeholder="e.g. Take after meals" value="{{ old('instructions') }}" class="{{ $input }}">
                        </div>

                        <div class="sm:col-span-2">
                            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Add to prescription</button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Lab requests --}}
            <div class="rounded-xl border bg-white p-6 shadow-sm">
                <h3 class="mb-4 font-semibold">Lab requests</h3>

                @if ($visit->labRequests->isNotEmpty())
                
                    <!-- <ul class="mb-5 divide-y text-sm">
                        @foreach ($visit->labRequests as $req)
                            <li class="py-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ $req->test->name }}</span>
                                    <span class="flex items-center gap-4">
                                        <x-badge :color="$req->status->value === 'pending' ? 'amber' : 'green'">{{ $req->status->label() }}</x-badge>
                                        @if ($editable && $req->status->value === 'pending')
                                            <form method="POST" action="{{ route('consultations.lab-requests.destroy', [$visit, $req]) }}">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-600 hover:underline">Remove</button>
                                            </form>
                                        @endif
                                    </span>
                                </div>

                                @if ($req->status->value === 'completed')
                                    <div class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2">{{ $req->result }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul> -->

                    <ul class="mb-5 divide-y text-sm">
                        @foreach ($visit->labRequests as $req)
                            <li class="py-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ $req->test->name }}</span>
                                    <span class="flex items-center gap-4">
                                        <x-badge :color="$req->status->value === 'pending' ? 'amber' : 'green'">{{ $req->status->label() }}</x-badge>
                                        <x-badge :color="$req->is_paid ? 'green' : 'amber'">{{ $req->is_paid ? 'Paid' : 'Unpaid' }}</x-badge>
                                        @if ($editable && $req->status->value === 'pending')
                                            <form method="POST" action="{{ route('consultations.lab-requests.destroy', [$visit, $req]) }}">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-600 hover:underline">Remove</button>
                                            </form>
                                        @endif
                                    </span>
                                </div>

                                @if ($req->status->value === 'completed')
                                    @if ($req->result)
                                        <div class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2">{{ $req->result }}</div>
                                    @endif
                                    @include('lab._attachments', ['attachments' => $req->attachments, 'canManage' => false])
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mb-4 text-sm text-slate-500">No tests requested.</p>
                @endif

                @if ($editable)
                    <form method="POST" action="{{ route('consultations.lab-requests.store', $visit) }}" class="rounded-lg bg-slate-50 p-4">
                        @csrf
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($tests as $test)
                                @continue($requested->contains($test->id))
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="lab_test_ids[]" value="{{ $test->id }}" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                    {{ $test->name }} <span class="text-slate-400">₦{{ number_format($test->price) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('lab_test_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                        <button class="mt-4 rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Request selected tests</button>
                    </form>
                @endif
            </div>

            @if ($editable)
                <div class="flex flex-wrap items-center gap-3">
                    <button form="visit-form" class="rounded-lg border border-teal-700 px-5 py-2.5 text-sm font-medium text-teal-700 hover:bg-teal-50">
                        Save notes
                    </button>
                    <button form="visit-form" formaction="{{ route('consultations.complete', $visit) }}"
                            onclick="return confirm('Complete this consultation? Prescriptions and lab requests will go to the pharmacy and lab.')"
                            class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
                        Complete consultation
                    </button>
                </div>
            @endif
        </div>

        {{-- History --}}
        <!--  -->
        {{-- History --}}
            <div>
                <div class="rounded-xl border bg-white shadow-sm">
                    <h3 class="border-b px-5 py-3 font-semibold">Previous visits</h3>
                    <ul class="divide-y text-sm">
                        @forelse ($history as $past)
                            <li class="px-5 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium">{{ $past->checked_in_at->format('d M Y') }}</p>
                                        <p class="text-slate-500">{{ $past->doctor?->name ?? 'No doctor recorded' }}</p>
                                        <p class="mt-1 text-slate-600">{{ $past->diagnosis ?: 'No diagnosis recorded' }}</p>
                                    </div>
                                    <a href="{{ route('consultations.history', ['visit' => $past, 'from' => $visit->id]) }}"
                                    class="shrink-0 rounded-lg border border-teal-700 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">
                                        View
                                    </a>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-500">First visit.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
    </div>
</x-app-layout>