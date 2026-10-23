<x-app-layout>
    <x-slot name="header">Consultation</x-slot>

    @php
        $patient = $visit->patient;
        $vitals  = $visit->vitals;
        $input   = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label   = 'block text-sm font-medium text-slate-700';
        $requested = $visit->labRequests->pluck('lab_test_id');
        $outstandingLabRequests = $visit->labRequests->filter(fn ($request) => $request->status->value !== 'completed');
        $prescriptionDrafts = $visit->prescriptions->filter(fn ($rx) => $rx->status->value === 'pending' && ! $rx->pharmacy_ordered_at);
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

    @if (session('success'))<div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    @if ($visit->status->value === 'waiting_doctor')
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="text-sm text-amber-950">This visit has not been opened by a doctor yet. Start it to enter the clinical workspace.</p>
            <form method="POST" action="{{ route('consultations.start', $visit) }}">
                @csrf
                <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">{{ $visit->is_emergency ? 'Start A&E care' : 'Start consultation' }}</button>
            </form>
        </div>
    @endif

    @if ($visit->is_emergency)
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-950">
            <strong>Active A&E episode.</strong> Save notes and orders as care proceeds. The visit remains open until you choose an outcome or admit the patient.
        </div>
        @if ($visit->emergencyObservations->isNotEmpty())
            <section class="mb-6 overflow-hidden rounded-xl border border-rose-100 bg-white shadow-sm">
                <div class="border-b border-rose-100 px-5 py-3"><h3 class="font-semibold text-slate-900">A&E observation history</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Recorded</th><th class="px-5 py-3">Vitals</th><th class="px-5 py-3">Notes</th><th class="px-5 py-3">By</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($visit->emergencyObservations as $observation)
                            <tr><td class="whitespace-nowrap px-5 py-3">{{ $observation->recorded_at->format('d M, g:i A') }}</td><td class="px-5 py-3">{{ $observation->temperature ? $observation->temperature.'°C · ' : '' }}{{ $observation->pulse ? 'P '.$observation->pulse.' · ' : '' }}{{ $observation->blood_pressure ? 'BP '.$observation->blood_pressure.' · ' : '' }}{{ $observation->spo2 ? 'SpO₂ '.$observation->spo2.'%' : '' }}</td><td class="px-5 py-3">{{ $observation->notes ?: '—' }}</td><td class="px-5 py-3">{{ $observation->recorder?->name ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif
    @endif

    <div class="mb-6">@include('emergency._progress-notes', ['visit' => $visit])</div>
    @if ($visit->is_emergency)
        <div class="mb-6">@include('emergency._items-used', ['visit' => $visit, 'emergencyStockItems' => $emergencyStockItems])</div>
    @endif

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
                            <label class="{{ $label }}">Clinical notes <span class="text-xs font-normal text-slate-500">(staff only)</span></label>
                            <textarea name="clinical_notes" rows="5" class="{{ $input }}">{{ old('clinical_notes', $visit->clinical_notes) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">These notes stay in the clinical record and are not shown in the patient portal.</p>
                        </div>
                        <div>
                            <label class="{{ $label }}">Note for the patient</label>
                            <textarea name="patient_visible_note" rows="4" class="{{ $input }}">{{ old('patient_visible_note', $visit->patient_visible_note) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Only this note is shown to the patient in their visit view after the consultation is completed.</p>
                        </div>
                        @if ($visit->is_emergency)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="{{ $label }}" for="disposition">A&E outcome <span class="text-red-600">*</span></label>
                                    <select id="disposition" name="disposition" class="{{ $input }}">
                                        <option value="">Choose an outcome when completing</option>
                                        <option value="discharged" @selected(old('disposition', $visit->disposition) === 'discharged')>Discharged</option>
                                        <option value="transferred" @selected(old('disposition', $visit->disposition) === 'transferred')>Transferred</option>
                                        <option value="deceased" @selected(old('disposition', $visit->disposition) === 'deceased')>Deceased in A&E</option>
                                    </select>
                                    @error('disposition')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}" for="disposition_note">Transfer details <span class="text-xs font-normal text-slate-500">(required for transfer)</span></label>
                                    <input id="disposition_note" name="disposition_note" maxlength="2000" value="{{ old('disposition_note', $visit->disposition_note) }}" placeholder="Receiving facility and handover details" class="{{ $input }}">
                                    @error('disposition_note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}" for="deceased_at">Date and time of death <span class="text-xs font-normal text-slate-500">(required if deceased)</span></label>
                                    <input id="deceased_at" name="deceased_at" type="datetime-local" max="{{ now()->format('Y-m-d\\TH:i') }}" value="{{ old('deceased_at') }}" class="{{ $input }}">
                                    @error('deceased_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <p class="text-xs text-slate-500">Choose an A&E outcome before completing the visit. Use “Admit to inpatient care” for admission. A deceased outcome will need a mortuary handoff.</p>
                        @endif
                    </form>
                @else
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-slate-500">Complaint</dt><dd class="font-medium">{{ $visit->complaint ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Diagnosis</dt><dd class="font-medium">{{ $visit->diagnosis ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Clinical notes</dt><dd class="whitespace-pre-line">{{ $visit->clinical_notes ?: '—' }}</dd></div>
                        @if ($visit->is_emergency)
                            <div><dt class="text-slate-500">A&E outcome</dt><dd class="font-medium">{{ $visit->disposition ? ucfirst($visit->disposition) : '—' }}</dd></div>
                            @if ($visit->disposition_note)<div><dt class="text-slate-500">Outcome details</dt><dd class="whitespace-pre-line">{{ $visit->disposition_note }}</dd></div>@endif
                        @endif
                        @if ($visit->patient_visible_note)
                            <div><dt class="text-slate-500">Note shared with patient</dt><dd class="whitespace-pre-line">{{ $visit->patient_visible_note }}</dd></div>
                        @endif
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
                                    <th class="pr-4">Duration</th><th class="pr-4">Qty</th><th class="pr-4">Charge</th><th class="pr-4">Status</th><th></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($visit->prescriptions as $rx)
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">{{ $rx->drug->display_name }}</td>
                                        <td class="pr-4">{{ $rx->dosage }}</td>
                                        <td class="pr-4">{{ $rx->frequency }}</td>
                                        <td class="pr-4">{{ $rx->duration }}</td>
                                        <td class="pr-4">{{ $rx->status->value === 'completed' ? ($rx->dispensable_quantity ?? $rx->quantity).' supplied / '.$rx->quantity : ($rx->dispensable_quantity ?? $rx->quantity).' reserved / '.$rx->quantity }}</td>
                                        <td class="pr-4">{{ $rx->invoiceItem ? '₦'.number_format((float) $rx->invoiceItem->total, 2) : '—' }}</td>
                                        <td class="pr-4">
                                            @if ($rx->status->value === 'completed')
                                                <x-badge color="green">Dispensed</x-badge>
                                            @elseif ($rx->pharmacy_ordered_at)
                                                <x-badge color="blue">Sent to pharmacy</x-badge>
                                            @else
                                                <x-badge color="slate">Draft</x-badge>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($rx->requires_in_facility_administration)
                                                <span class="block text-xs text-blue-700">Nurse: {{ $rx->administered_count }}/{{ $rx->required_administration_count }} doses</span>
                                            @endif
                                            @if ($editable && $rx->status->value === 'pending' && ! $rx->pharmacy_ordered_at)
                                                <form method="POST" action="{{ route('consultations.prescriptions.destroy', [$visit, $rx]) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="text-xs text-red-600 hover:underline">Remove</button>
                                                </form>
                                            @elseif ($editable && $rx->pharmacy_ordered_at && $rx->status->value === 'pending')
                                                <span class="text-xs text-slate-500">In pharmacy queue</span>
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

                @if ($editable && $prescriptionDrafts->isNotEmpty())
                    <form method="POST" action="{{ route('consultations.prescriptions.place-order', $visit) }}" class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        @csrf
                        <p class="text-sm text-blue-950">Send {{ $prescriptionDrafts->count() }} pending drug{{ $prescriptionDrafts->count() === 1 ? '' : 's' }} or injection{{ $prescriptionDrafts->count() === 1 ? '' : 's' }} to pharmacy now. The consultation can remain open.</p>
                        <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Place order for prescription</button>
                    </form>
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
                                        {{ $drug->display_name }}: {{ $drug->available_quantity }} available · ₦{{ number_format((float) $drug->unit_price, 2) }} / unit
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

                        <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-white p-3">
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" name="requires_in_facility_administration" value="1"
                                       @checked(old('requires_in_facility_administration', $visit->is_emergency))
                                       class="mt-0.5 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                <span>
                                    <span class="font-medium">Give this medicine at the hospital</span>
                                    <span class="block text-xs text-slate-500">After pharmacy dispenses it, add a nursing administration task. Leave unchecked for take-home medicine.</span>
                                </span>
                            </label>
                            <div class="mt-3 max-w-xs">
                                <label class="{{ $label }}" for="required_administration_count">Number of administrations</label>
                                <input id="required_administration_count" name="required_administration_count" type="number" min="1" max="100"
                                       value="{{ old('required_administration_count', 1) }}" class="{{ $input }}">
                                @error('required_administration_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
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

                @if ($outstandingLabRequests->isNotEmpty())
                    <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        This consultation must remain open until results are entered or uploaded for all {{ $outstandingLabRequests->count() }} outstanding test(s).
                    </p>
                @endif
                @error('lab_requests')
                    <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</p>
                @enderror

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
                            onclick="return confirm('{{ $visit->is_emergency ? 'Close A&E care with the selected outcome?' : 'Complete the consultation and send orders to pharmacy and lab?' }}')"
                            @disabled($outstandingLabRequests->isNotEmpty())
                            class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $visit->is_emergency ? 'Complete A&E visit' : 'Complete consultation' }}
                    </button>
                    <p class="basis-full text-xs text-slate-500">{{ $visit->is_emergency ? 'A&E care stays active while the doctor records notes, orders, and outcome. Required medicines must be dispensed and administered before discharge.' : 'Completing the consultation sends orders downstream. Nursing administration and patient discharge are tracked separately.' }}</p>
                </div>

                <section class="rounded-xl border border-teal-200 bg-teal-50/60 p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-teal-950">Admit to inpatient care</h3>
                            <p class="mt-1 max-w-3xl text-sm text-teal-900">Admission closes this consultation and opens an inpatient record linked to the same visit, prescriptions, investigations, and clinical history.</p>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-teal-800">Doctor decision</span>
                    </div>

                    @if ($wardDepartments->isEmpty())
                        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">No department is currently enabled for inpatient admission. Ask an administrator to mark the appropriate department as having a ward.</p>
                    @else
                        <form method="POST" action="{{ route('consultations.admit', $visit) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label for="admission_department" class="{{ $label }}">Ward department <span class="text-red-600">*</span></label>
                                <select id="admission_department" name="department_id" required class="{{ $input }}">
                                    <option value="">Select a ward department</option>
                                    @foreach ($wardDepartments as $wardDepartment)
                                        <option value="{{ $wardDepartment->id }}" @selected(old('department_id') == $wardDepartment->id)>{{ $wardDepartment->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="admission_diagnosis" class="{{ $label }}">Admission diagnosis <span class="text-red-600">*</span></label>
                                <input id="admission_diagnosis" name="admission_diagnosis" required maxlength="1000" value="{{ old('admission_diagnosis', $visit->diagnosis) }}" class="{{ $input }}">
                                @error('admission_diagnosis')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="admission_reason" class="{{ $label }}">Reason inpatient care is required <span class="text-red-600">*</span></label>
                                <textarea id="admission_reason" name="admission_reason" rows="3" required maxlength="5000" class="{{ $input }}">{{ old('admission_reason') }}</textarea>
                                @error('admission_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="initial_treatment_plan" class="{{ $label }}">Initial inpatient treatment plan <span class="text-red-600">*</span></label>
                                <textarea id="initial_treatment_plan" name="initial_treatment_plan" rows="4" required maxlength="5000" class="{{ $input }}">{{ old('initial_treatment_plan') }}</textarea>
                                @error('initial_treatment_plan')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="expected_discharge_date" class="{{ $label }}">Expected discharge date <span class="font-normal text-slate-500">(optional)</span></label>
                                <input id="expected_discharge_date" type="date" name="expected_discharge_date" min="{{ today()->toDateString() }}" value="{{ old('expected_discharge_date') }}" class="{{ $input }}">
                                @error('expected_discharge_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" onclick="return confirm('Admit this patient? The current consultation will close and an inpatient record will open.')" class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Admit patient</button>
                            </div>
                        </form>
                    @endif
                </section>
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
