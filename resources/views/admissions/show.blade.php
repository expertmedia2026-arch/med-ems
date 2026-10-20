<x-app-layout>
    <x-slot name="header">Inpatient record</x-slot>

    @php
        $isActive = $admission->status->value === 'active';
        $visit = $admission->visit;
        $input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admissions.index') }}" class="text-sm font-medium text-teal-700 hover:underline">← Admissions</a>
        <div class="flex flex-wrap gap-2">
            @if ($visit->prescriptions->contains(fn ($prescription) => $prescription->requires_in_facility_administration))
                <a href="{{ route('nursing.administrations.show', $visit) }}" class="rounded-lg border border-teal-700 px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50">Open nursing care</a>
            @endif
            <a href="{{ route('patients.show', $admission->patient) }}" class="rounded-lg border px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Patient record</a>
        </div>
    </div>

    @if (session('success'))<div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">Please review the information below.</p><ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-sm font-medium text-teal-700">{{ $admission->admission_number }} · {{ $admission->patient->hospital_number }}</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ $admission->patient->full_name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ ucfirst($admission->patient->gender) }} · {{ $admission->patient->age }} years · {{ $admission->department->name }} ward</p>
            </div>
            <x-badge :color="$admission->status->color()">{{ $admission->status->label() }}</x-badge>
        </div>
        @if ($admission->patient->allergies)
            <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Allergies:</strong> {{ $admission->patient->allergies }}</p>
        @endif
        <dl class="mt-5 grid gap-4 border-t pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-slate-500">Attending doctor</dt><dd class="mt-1 font-medium">{{ $admission->attendingDoctor->name }}</dd></div>
            <div><dt class="text-slate-500">Admitted</dt><dd class="mt-1 font-medium">{{ $admission->admitted_at->format('d M Y, g:i A') }}</dd></div>
            <div><dt class="text-slate-500">Expected discharge</dt><dd class="mt-1 font-medium">{{ $admission->expected_discharge_date?->format('d M Y') ?? 'Not set' }}</dd></div>
            <div><dt class="text-slate-500">Admission reason</dt><dd class="mt-1 font-medium">{{ $admission->admission_reason }}</dd></div>
        </dl>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">Admission assessment and plan</h3>
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <div><h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admission diagnosis</h4><p class="mt-2 whitespace-pre-line text-sm">{{ $admission->admission_diagnosis }}</p></div>
                    <div><h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Initial treatment plan</h4><p class="mt-2 whitespace-pre-line text-sm">{{ $admission->initial_treatment_plan }}</p></div>
                    <div class="md:col-span-2"><h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Consultation notes · linked source</h4><p class="mt-2 whitespace-pre-line text-sm">{{ $visit->clinical_notes ?: 'No additional clinical notes recorded.' }}</p><p class="mt-2 text-xs text-slate-500">Original complaint: {{ $visit->complaint ?: '—' }}</p></div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="border-b px-5 py-4">
                    <h3 class="font-semibold text-slate-900">Inpatient observations</h3>
                    <p class="mt-1 text-sm text-slate-500">Record each set of bedside vitals with its own time and nurse. Consultation triage vitals remain in the source visit.</p>
                </div>
                @if ($isActive && auth()->user()->hasRole('admin', 'nurse'))
                    <form method="POST" action="{{ route('admissions.vitals.store', $admission) }}" class="grid gap-4 border-b bg-slate-50 p-5 sm:grid-cols-2 lg:grid-cols-4">
                        @csrf
                        <label class="{{ $label }}">Temperature (°C) <span class="text-red-600">*</span><input name="temperature" type="number" step="0.1" min="30" max="45" required value="{{ old('temperature') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Pulse (bpm) <span class="text-red-600">*</span><input name="pulse" type="number" min="20" max="250" required value="{{ old('pulse') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Respiratory rate<input name="respiratory_rate" type="number" min="5" max="80" value="{{ old('respiratory_rate') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Oxygen saturation (%)<input name="spo2" type="number" min="50" max="100" value="{{ old('spo2') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Systolic blood pressure<input name="bp_systolic" type="number" min="50" max="300" value="{{ old('bp_systolic') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Diastolic blood pressure<input name="bp_diastolic" type="number" min="30" max="200" value="{{ old('bp_diastolic') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Weight (kg)<input name="weight" type="number" step="0.01" min="0.5" max="400" value="{{ old('weight') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Height (cm)<input name="height" type="number" step="0.01" min="20" max="250" value="{{ old('height') }}" class="{{ $input }}"></label>
                        <label class="{{ $label }} sm:col-span-2 lg:col-span-3">Observation note<textarea name="notes" rows="2" maxlength="1000" class="{{ $input }}" placeholder="Optional context or action taken">{{ old('notes') }}</textarea></label>
                        <div class="flex items-end justify-end"><button class="w-full rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Record vitals</button></div>
                        @foreach (['temperature', 'pulse', 'respiratory_rate', 'spo2', 'bp_systolic', 'bp_diastolic', 'weight', 'height', 'notes'] as $field)
                            @error($field)<p class="text-sm text-red-600 sm:col-span-2 lg:col-span-4">{{ $message }}</p>@enderror
                        @endforeach
                    </form>
                @endif
                <div class="divide-y divide-slate-100">
                    @forelse ($admission->vitals as $vital)
                        <article class="p-5">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $vital->recorded_at->format('D, d M Y · g:i A') }}</p>
                                <p class="text-xs text-slate-500">Recorded by {{ $vital->recorder->name }}</p>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4 lg:grid-cols-7">
                                <div><dt class="text-xs text-slate-500">Temperature</dt><dd class="font-semibold">{{ number_format((float) $vital->temperature, 1) }} °C</dd></div>
                                <div><dt class="text-xs text-slate-500">Pulse</dt><dd class="font-semibold">{{ $vital->pulse }} bpm</dd></div>
                                <div><dt class="text-xs text-slate-500">Respiratory rate</dt><dd class="font-semibold">{{ $vital->respiratory_rate ?? '—' }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Oxygen saturation</dt><dd class="font-semibold">{{ $vital->spo2 ? $vital->spo2.'%' : '—' }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Blood pressure</dt><dd class="font-semibold">{{ $vital->blood_pressure ?? '—' }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Weight</dt><dd class="font-semibold">{{ $vital->weight ? $vital->weight.' kg' : '—' }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Height</dt><dd class="font-semibold">{{ $vital->height ? $vital->height.' cm' : '—' }}</dd></div>
                            </dl>
                            @if ($vital->notes)<p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $vital->notes }}</p>@endif
                        </article>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-500">No inpatient observations have been recorded yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="border-b px-5 py-4"><h3 class="font-semibold text-slate-900">Inpatient progress notes</h3><p class="mt-1 text-sm text-slate-500">Clinical and nursing notes are time-stamped and remain part of this admission record.</p></div>
                @if ($isActive && auth()->user()->hasRole('doctor', 'nurse'))
                    <form method="POST" action="{{ route('admissions.notes.store', $admission) }}" class="border-b bg-slate-50 p-5">
                        @csrf
                        <label class="{{ $label }}">{{ auth()->user()->hasRole('nurse') ? 'Nursing progress note' : 'Medical progress note' }} <span class="text-red-600">*</span><textarea name="note" rows="3" required maxlength="5000" class="{{ $input }}" placeholder="Record the relevant inpatient update and care provided">{{ old('note') }}</textarea></label>
                        @error('note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-3 flex justify-end"><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Add progress note</button></div>
                    </form>
                @endif
                <ol class="divide-y divide-slate-100">
                    @forelse ($admission->notes as $note)
                        <li class="flex gap-3 p-5">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $note->note_type === 'nursing' ? 'bg-slate-400' : 'bg-teal-600' }}"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold text-slate-900">{{ $note->note_type === 'nursing' ? 'Nursing note' : 'Medical note' }} · {{ $note->author->name }}</p><time class="text-xs text-slate-500">{{ $note->created_at->format('d M Y, g:i A') }}</time></div>
                                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $note->note }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">No inpatient progress notes have been recorded yet.</li>
                    @endforelse
                </ol>
            </section>

            <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                    <div><h3 class="font-semibold text-slate-900">Investigations and results</h3><p class="mt-1 text-sm text-slate-500">Results remain connected to the consultation and route to the attending doctor for review.</p></div>
                    @if ($pendingResults->isNotEmpty())<span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">{{ $pendingResults->count() }} to review</span>@endif
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($visit->labRequests as $lab)
                        <article class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h4 class="font-medium text-slate-900">{{ $lab->test?->name ?? 'Laboratory test' }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ $lab->status->label() }}@if ($lab->completed_at) · completed {{ $lab->completed_at->format('d M Y, g:i A') }} by {{ $lab->processor?->name ?? 'laboratory' }}@endif</p>
                                </div>
                                @if ($lab->status === \App\Enums\WorkStatus::Completed)
                                    @if ($lab->result_reviewed_at)
                                        <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-800">Reviewed {{ $lab->result_reviewed_at->format('d M Y') }} by {{ $lab->resultReviewer?->name ?? 'doctor' }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">Awaiting doctor review</span>
                                    @endif
                                @endif
                            </div>
                            @if ($lab->result)
                                <div class="mt-3 rounded-lg bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Result</p><p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $lab->result }}</p></div>
                            @endif
                            @if ($lab->attachments->isNotEmpty())
                                <ul class="mt-3 space-y-1 text-sm">@foreach ($lab->attachments as $attachment)<li><a href="{{ route('lab.attachments.show', $attachment) }}" class="font-medium text-teal-700 hover:underline">{{ $attachment->original_name }}</a></li>@endforeach</ul>
                            @endif
                            @if ($canManage && $lab->status === \App\Enums\WorkStatus::Completed && ! $lab->result_reviewed_at)
                                <form method="POST" action="{{ route('admissions.lab-results.review', [$admission, $lab]) }}" class="mt-4 rounded-lg border border-teal-100 bg-teal-50/50 p-4">
                                    @csrf
                                    <label class="{{ $label }}">Doctor’s review note <span class="font-normal text-slate-500">(optional)</span><textarea name="review_note" rows="2" maxlength="5000" class="{{ $input }}" placeholder="Record the clinical interpretation or next action"></textarea></label>
                                    <div class="mt-3 flex justify-end"><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Mark reviewed</button></div>
                                </form>
                            @elseif ($lab->result_review_note)
                                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm"><strong>Doctor’s review note:</strong> {{ $lab->result_review_note }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-slate-500">No laboratory investigations are linked to this admission.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="border-b px-5 py-4"><h3 class="font-semibold text-slate-900">Inpatient medication orders</h3><p class="mt-1 text-sm text-slate-500">Consultation and inpatient orders stay on the linked visit, so pharmacy and ward nursing can follow them through dispensing and administration.</p></div>
                @if ($isActive && $canPrescribe)
                    <form method="POST" action="{{ route('admissions.prescriptions.store', $admission) }}" class="grid gap-4 border-b bg-slate-50 p-5 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2">
                            <label class="{{ $label }}">Medicine or injection <span class="text-red-600">*</span>
                                <select name="drug_id" required class="{{ $input }}">
                                    <option value="">Select medicine</option>
                                    @foreach ($drugs as $drug)
                                        <option value="{{ $drug->id }}" @selected(old('drug_id') == $drug->id)>{{ $drug->display_name }} · {{ $drug->available_quantity }} available</option>
                                    @endforeach
                                </select>
                            </label>
                            @error('drug_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            @if ($drugs->isEmpty())<p class="mt-1 text-sm text-amber-700">No active medicines with available stock are in the catalogue.</p>@endif
                        </div>
                        <label class="{{ $label }}">Dose <span class="text-red-600">*</span><input name="dosage" required maxlength="100" value="{{ old('dosage') }}" placeholder="e.g. 500 mg or 1 vial" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Frequency <span class="text-red-600">*</span><select name="frequency" required class="{{ $input }}">@foreach (['Once daily', 'Twice daily', 'Three times daily', 'Four times daily', 'At night', 'When required'] as $frequency)<option @selected(old('frequency') === $frequency)>{{ $frequency }}</option>@endforeach</select></label>
                        <label class="{{ $label }}">Duration <span class="text-red-600">*</span><input name="duration" required maxlength="100" value="{{ old('duration') }}" placeholder="e.g. 3 days" class="{{ $input }}"></label>
                        <label class="{{ $label }}">Quantity for pharmacy <span class="text-red-600">*</span><input name="quantity" type="number" min="1" max="1000" required value="{{ old('quantity') }}" class="{{ $input }}">@error('quantity')<span class="mt-1 block text-red-600">{{ $message }}</span>@enderror</label>
                        <label class="{{ $label }} sm:col-span-2">Order instructions / administration route<input name="instructions" maxlength="255" value="{{ old('instructions') }}" placeholder="e.g. IV, dilute as directed; include relevant nursing instructions" class="{{ $input }}"></label>
                        <div class="rounded-lg border border-teal-200 bg-white p-4 sm:col-span-2">
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" name="requires_in_facility_administration" value="1" @checked(old('requires_in_facility_administration', true)) class="mt-0.5 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                <span><span class="font-semibold text-slate-900">Nurse to administer in this ward</span><span class="mt-1 block text-xs text-slate-500">Pharmacy must dispense the order first. The assigned ward nursing team will then record each administration.</span></span>
                            </label>
                            <label class="{{ $label }} mt-3 block max-w-xs">Number of nursing administrations<input name="required_administration_count" type="number" min="1" max="100" value="{{ old('required_administration_count', 1) }}" class="{{ $input }}"></label>
                            @error('required_administration_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2 flex justify-end"><button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Add inpatient order</button></div>
                    </form>
                @endif
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Medicine</th><th class="px-4 py-3">Directions</th><th class="px-4 py-3">Dispensing</th><th class="px-4 py-3">Nursing doses</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($visit->prescriptions as $prescription)
                                <tr><td class="px-4 py-3 font-medium">{{ $prescription->drug->display_name }}</td><td class="px-4 py-3">{{ $prescription->dosage }} · {{ $prescription->frequency }} · {{ $prescription->duration }}</td><td class="px-4 py-3">{{ $prescription->status->label() }}</td><td class="px-4 py-3">{{ $prescription->requires_in_facility_administration ? $prescription->administrations->where('status', 'administered')->count().' / '.$prescription->required_administration_count : 'Not required' }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No medication orders are linked to this consultation.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            @if ($isActive && $canManage)
                <section class="rounded-xl border border-teal-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-slate-900">Complete discharge</h3>
                    <p class="mt-1 text-sm text-slate-500">Record the clinical outcome and continuity plan before closing this inpatient stay.</p>
                    @if ($unresolvedResults->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $unresolvedResults->count() }} investigation(s) are outstanding or need review. Record a follow-up owner and plan below.</div>
                    @endif
                    <form method="POST" action="{{ route('admissions.discharge', $admission) }}" class="mt-4 space-y-4">
                        @csrf
                        <label class="{{ $label }}">Discharge diagnosis <span class="text-red-600">*</span><textarea name="discharge_diagnosis" rows="2" required maxlength="2000" class="{{ $input }}">{{ old('discharge_diagnosis', $admission->discharge_diagnosis) }}</textarea></label>
                        <label class="{{ $label }}">Discharge summary <span class="text-red-600">*</span><textarea name="discharge_summary" rows="4" required maxlength="10000" class="{{ $input }}" placeholder="Clinical course and condition at discharge">{{ old('discharge_summary', $admission->discharge_summary) }}</textarea></label>
                        <label class="{{ $label }}">Medication reconciliation / discharge medicines <span class="text-red-600">*</span><textarea name="discharge_medications" rows="3" required maxlength="5000" class="{{ $input }}" placeholder="Include the discharge medication plan or state no changes">{{ old('discharge_medications', $admission->discharge_medications) }}</textarea></label>
                        <label class="{{ $label }}">Follow-up plan and return precautions <span class="text-red-600">*</span><textarea name="follow_up_instructions" rows="3" required maxlength="5000" class="{{ $input }}">{{ old('follow_up_instructions', $admission->follow_up_instructions) }}</textarea></label>
                        <label class="{{ $label }}">Instructions to share with the patient <span class="text-red-600">*</span><textarea name="patient_discharge_instructions" rows="3" required maxlength="5000" class="{{ $input }}" placeholder="Patient-facing discharge message for the portal">{{ old('patient_discharge_instructions', $admission->patient_discharge_instructions) }}</textarea></label>
                        @if ($unresolvedResults->isNotEmpty())
                            <label class="{{ $label }}">Outstanding results follow-up <span class="text-red-600">*</span><textarea name="pending_results_plan" rows="3" required maxlength="5000" class="{{ $input }}" placeholder="Who will review pending results and how will the patient be contacted?">{{ old('pending_results_plan', $admission->pending_results_plan) }}</textarea></label>
                        @endif
                        <button onclick="return confirm('Complete discharge and close this inpatient admission?')" class="w-full rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Discharge patient</button>
                    </form>
                </section>
            @elseif (! $isActive)
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-slate-900">Discharge record</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $admission->discharged_at?->format('d M Y, g:i A') }} · {{ $admission->dischargedBy?->name ?? '—' }}</p>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div><dt class="font-medium text-slate-500">Discharge diagnosis</dt><dd class="mt-1 whitespace-pre-line">{{ $admission->discharge_diagnosis }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Clinical summary</dt><dd class="mt-1 whitespace-pre-line">{{ $admission->discharge_summary }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Discharge medicines</dt><dd class="mt-1 whitespace-pre-line">{{ $admission->discharge_medications }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Follow-up instructions</dt><dd class="mt-1 whitespace-pre-line">{{ $admission->follow_up_instructions }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Instructions shared with patient</dt><dd class="mt-1 whitespace-pre-line">{{ $admission->patient_discharge_instructions }}</dd></div>
                        @if ($admission->pending_results_plan)<div><dt class="font-medium text-slate-500">Outstanding results plan</dt><dd class="mt-1 whitespace-pre-line">{{ $admission->pending_results_plan }}</dd></div>@endif
                    </dl>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
