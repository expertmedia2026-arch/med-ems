<x-app-layout>
    <x-slot name="header">Accident & Emergency (A&amp;E)</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">A&amp;E department</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Accident &amp; Emergency (A&amp;E)</h1>
                <p class="mt-1 text-sm text-slate-500">Register arrivals, prioritize triage, and follow patients through A&amp;E.</p>
            </div>
            @if (auth()->user()->canAccessEmergencyPharmacy())
                <a href="{{ route('emergency.pharmacy.index') }}" class="rounded-lg border border-rose-700 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">A&amp;E Pharmacy and stock</a>
            @endif
            @if (auth()->user()->hasRole('admin', 'nurse'))
                <a href="{{ route('triage.index') }}" class="rounded-lg border border-teal-700 bg-white px-4 py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-50">Open triage queue</a>
            @endif
        </div>

        @if (auth()->user()->canRegisterEmergencyArrival())
            <section class="rounded-xl border border-teal-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <h2 class="font-semibold text-slate-900">Register an A&amp;E arrival</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Find the patient record first. If they are new,
                        @if (auth()->user()->hasRole('admin', 'receptionist'))
                            <a href="{{ route('patients.create') }}" class="font-medium text-teal-700 underline">register them from Patients</a>
                        @else
                            register them with the front desk
                        @endif
                        , then return here and search using the hospital number on the new patient file.
                        Select the matching patient and mark the BID box for a brought-in-dead arrival; the file will wait for an A&amp;E doctor to confirm death and authorize the next step.
                    </p>
                </div>
                <form method="GET" action="{{ route('emergency.index') }}" class="mt-4 flex max-w-2xl gap-2">
                    <label class="sr-only" for="patient-search">Search patients</label>
                    <input id="patient-search" name="q" type="search" value="{{ $search }}" placeholder="Search name, hospital number or phone" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                    <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Search</button>
                </form>

                @if ($search !== '')
                    <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                        @forelse ($patients as $patient)
                            <div class="flex flex-col gap-4 border-b border-slate-100 p-4 last:border-b-0 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-48">
                                    <p class="font-semibold text-slate-900">{{ $patient->full_name }}</p>
                                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $patient->hospital_number }} · {{ $patient->identity_status === 'unidentified' ? 'Identity unconfirmed' : ucfirst($patient->gender).', '.($patient->age ?? 'Age not recorded').' yrs' }}</p>
                                    @if ($patient->allergies)
                                        <p class="mt-1 text-xs font-medium text-rose-700">Allergies recorded: {{ $patient->allergies }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('emergency.store', $patient) }}" class="grid flex-1 gap-3 sm:grid-cols-[minmax(12rem,1fr)_12rem_auto]">
                                    @csrf
                                    <div>
                                        <label for="complaint-{{ $patient->id }}" class="sr-only">Presenting complaint for {{ $patient->full_name }}</label>
                                        <input id="complaint-{{ $patient->id }}" name="complaint" value="{{ old('complaint') }}" required maxlength="1000" placeholder="Presenting complaint" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                    </div>
                                    <div>
                                        <label for="arrival-{{ $patient->id }}" class="sr-only">Arrival method for {{ $patient->full_name }}</label>
                                        <select id="arrival-{{ $patient->id }}" name="arrival_mode" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                            <option value="walk_in">Walk-in</option>
                                            <option value="ambulance">Ambulance</option>
                                            <option value="referred">Referred</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    @if ($patient->identity_status !== 'unidentified')
                                        <label class="flex items-center gap-2 text-xs font-medium text-rose-800 sm:col-span-3"><input type="checkbox" name="brought_in_dead" value="1" @checked(old('brought_in_dead')) class="rounded border-rose-300 text-rose-700"> Brought in dead (BID) — doctor confirmation required</label>
                                    @else
                                        <p class="text-xs text-amber-800 sm:col-span-3">Complete the patient’s identity from their file before opening a BID case.</p>
                                    @endif
                                    <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 sm:col-span-3">Register A&amp;E arrival</button>
                                </form>
                            </div>
                        @empty
                            <p class="p-5 text-sm text-slate-500">No matching patient found. Register the patient before starting the A&amp;E visit.</p>
                        @endforelse
                    </div>
                    @error('complaint')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                    @error('arrival_mode')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                @endif

                <details class="mt-5 rounded-lg border border-amber-200 bg-amber-50/60 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-800">Register an unidentified arrival</summary>
                    <p class="mt-2 text-xs text-slate-600">This creates a temporary patient record for an unidentified person who needs emergency care. For a BID case, first register or find the patient record, then use its hospital number in the search above and mark BID.</p>
                    <form id="unidentified-arrival-form" method="POST" action="{{ route('emergency.unidentified.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @csrf
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label for="unknown-complaint" class="block text-sm font-medium text-slate-700">Presenting complaint <span class="text-rose-600">*</span></label>
                            <input id="unknown-complaint" name="complaint" value="{{ old('complaint') }}" required maxlength="1000" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @error('complaint')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="unknown-arrival" class="block text-sm font-medium text-slate-700">Arrival method</label>
                            <select id="unknown-arrival" name="arrival_mode" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                @foreach (['walk_in' => 'Walk-in', 'ambulance' => 'Ambulance', 'referred' => 'Referred', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('arrival_mode') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('arrival_mode')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="unknown-gender" class="block text-sm font-medium text-slate-700">Sex, if known</label>
                            <select id="unknown-gender" name="gender" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <option value="unknown" @selected(old('gender', 'unknown') === 'unknown')>Unknown</option>
                                <option value="male" @selected(old('gender') === 'male')>Male</option>
                                <option value="female" @selected(old('gender') === 'female')>Female</option>
                            </select>
                            @error('gender')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="estimated-age" class="block text-sm font-medium text-slate-700">Approximate age</label>
                            <input id="estimated-age" name="estimated_age" type="number" min="0" max="120" value="{{ old('estimated_age') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @error('estimated_age')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="unknown-next-of-kin" class="block text-sm font-medium text-slate-700">Next of kin, if known</label>
                            <input id="unknown-next-of-kin" name="next_of_kin_name" value="{{ old('next_of_kin_name') }}" maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        </div>
                        <div>
                            <label for="unknown-next-of-kin-phone" class="block text-sm font-medium text-slate-700">Next of kin phone</label>
                            <input id="unknown-next-of-kin-phone" name="next_of_kin_phone" value="{{ old('next_of_kin_phone') }}" maxlength="20" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @error('next_of_kin_phone')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Register unidentified arrival</button>
                        </div>
                    </form>
                </details>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900">Active A&amp;E visits</h2>
                <p class="mt-1 text-sm text-slate-500">Visits needing triage or clinical follow-up appear first, ordered by recorded triage priority.</p>
                @if ($errors->any())<div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }} Open the relevant arrival or BID form below to correct it.</div>@endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Patient</th>
                            <th class="px-5 py-3">Complaint / arrival</th>
                            <th class="px-5 py-3">Priority</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Waiting</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($visits as $visit)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900">{{ $visit->patient->full_name }}</p>
                                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $visit->patient->hospital_number }}</p>
                                </td>
                                <td class="max-w-sm px-5 py-4">
                                    <p class="text-slate-800">{{ $visit->complaint }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ str_replace('_', ' ', ucfirst($visit->arrival_mode ?? 'arrival not recorded')) }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    @if ($visit->is_brought_in_dead)
                                        <x-badge color="red">BID · confirmation required</x-badge>
                                    @elseif ($visit->triage_priority)
                                        <x-badge :color="$visit->triage_priority === 'critical' ? 'red' : ($visit->triage_priority === 'high' ? 'amber' : 'teal')">{{ ucfirst($visit->triage_priority) }}</x-badge>
                                    @else
                                        <x-badge color="amber">Needs triage</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-4"><x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge></td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $visit->checked_in_at->diffForHumans(short: true) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($visit->is_brought_in_dead && auth()->user()->hasRole('doctor') && $visit->status->value === 'waiting_doctor')
                                        <details class="min-w-64 text-left">
                                            <summary class="cursor-pointer font-semibold text-rose-700 hover:underline">Confirm death / disposition</summary>
                                            <form method="POST" action="{{ route('emergency.brought-in-dead.confirm', $visit) }}" class="mt-3 min-w-72 space-y-3 rounded-lg border border-rose-200 bg-rose-50 p-4 sm:w-96">
                                                @csrf
                                                <p class="text-xs text-slate-600">This records the time of your confirmation automatically. The mortuary will not accept the body until a doctor confirms and approves transfer.</p>
                                                <label class="block text-xs font-medium text-slate-700">Cause / clinical circumstances of death <textarea name="cause_of_death" required minlength="2" maxlength="5000" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('cause_of_death') }}</textarea></label>
                                                <label class="block text-xs font-medium text-slate-700">Clinical confirmation note <textarea name="death_clinical_summary" required minlength="2" maxlength="10000" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="Document the examination and confirmation.">{{ old('death_clinical_summary') }}</textarea></label>
                                                <label class="block text-xs font-medium text-slate-700">Disposition<select name="disposition" data-bid-disposition required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="mortuary_transfer">Transfer to hospital mortuary</option><option value="direct_family_release">Direct release to family / authorized representative</option></select></label>
                                                <label data-bid-family-fields class="hidden space-y-2 rounded border bg-white p-3 text-xs font-medium text-slate-700">
                                                    <span>Recipient and release authority</span>
                                                    <input name="released_to" maxlength="150" placeholder="Recipient name" class="w-full rounded-lg border-slate-300 text-sm">
                                                    <input name="recipient_relationship" maxlength="100" placeholder="Relationship to patient" class="w-full rounded-lg border-slate-300 text-sm">
                                                    <input name="recipient_id_reference" maxlength="120" placeholder="Recipient ID / reference (optional)" class="w-full rounded-lg border-slate-300 text-sm">
                                                    <input name="release_authority" maxlength="150" placeholder="Release authority / document reference" class="w-full rounded-lg border-slate-300 text-sm">
                                                    <textarea name="release_note" maxlength="2000" rows="2" placeholder="Release note (optional)" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                                                </label>
                                                <label class="block text-xs font-medium text-slate-700">Transfer approval note / reference (optional)<input name="transfer_approval_note" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                                                <label class="flex items-start gap-2 text-xs font-semibold text-rose-900"><input type="checkbox" name="approval_confirmed" value="1" required class="mt-0.5 rounded border-rose-300 text-rose-700"> I confirm death and authorize the selected disposition. For a mortuary transfer, this records my transfer approval.</label>
                                                <button class="w-full rounded-lg bg-rose-700 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-800">Confirm death and approve disposition</button>
                                            </form>
                                        </details>
                                    @elseif ($visit->is_brought_in_dead)
                                        <span class="text-xs text-amber-700">Waiting for A&amp;E doctor confirmation</span>
                                    @elseif ($visit->status->value === 'waiting_triage' && auth()->user()->hasRole('admin', 'nurse'))
                                        <a href="{{ route('triage.show', $visit) }}" class="font-semibold text-teal-700 hover:underline">Triage</a>
                                    @elseif ($visit->status->value === 'waiting_doctor' && auth()->user()->hasRole('doctor'))
                                        <form method="POST" action="{{ route('consultations.start', $visit) }}">
                                            @csrf
                                            <button class="font-semibold text-teal-700 hover:underline">Start A&amp;E care</button>
                                        </form>
                                    @elseif ($visit->status->value === 'in_consultation' && auth()->user()->hasRole('doctor'))
                                        <a href="{{ route('consultations.show', $visit) }}" class="font-semibold text-teal-700 hover:underline">Open consultation</a>
                                    @elseif (in_array($visit->status->value, ['waiting_doctor', 'in_consultation']) && auth()->user()->hasRole('admin', 'nurse'))
                                        <a href="{{ route('triage.show', $visit) }}" class="font-semibold text-teal-700 hover:underline">Monitor vitals</a>
                                        @if ($visit->status->value === 'in_consultation' && $visit->nursing_orders_count)
                                            <span class="mx-1 text-slate-300">·</span>
                                            <a href="{{ route('nursing.administrations.show', $visit) }}" class="font-semibold text-teal-700 hover:underline">Nursing care</a>
                                        @endif
                                    @elseif ($visit->status->value === 'inpatient' && $visit->admission && auth()->user()->hasRole('doctor', 'admin', 'nurse'))
                                        <a href="{{ route('admissions.show', $visit->admission) }}" class="font-semibold text-teal-700 hover:underline">Open admission</a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No active A&amp;E visits.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($visits->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $visits->links() }}</div>@endif
        </section>

        <section class="overflow-hidden rounded-xl border border-teal-200 bg-white shadow-sm">
            <div class="border-b border-teal-100 p-5">
                <h2 class="font-semibold text-slate-900">Mortuary handoffs</h2>
                <p class="mt-1 text-sm text-slate-500">Hospital deaths approved by a doctor and awaiting physical transfer and receipt.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($mortuaryHandoffs as $case)
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $case->patient->full_name }} · <span class="font-mono text-sm">{{ $case->case_number }}</span></p>
                            <p class="mt-1 text-xs text-slate-500">{{ $case->patient->hospital_number }} · Deceased {{ $case->deceased_at->format('d M Y, g:i A') }}</p>
                            <p class="mt-1 text-xs text-teal-700">Transfer approved by {{ $case->mortuaryTransferApprovedBy?->name ?? $case->declaredBy?->name ?? 'doctor' }}@if ($case->mortuary_transfer_approved_at) · {{ $case->mortuary_transfer_approved_at->format('d M Y, g:i A') }}@endif</p>
                        </div>
                        @if (auth()->user()->canReceiveEmergencyMortuaryHandoff())
                            <form method="POST" action="{{ route('emergency.mortuary.receive', $case) }}" class="grid w-full gap-3 sm:grid-cols-[minmax(11rem,1fr)_minmax(11rem,1fr)_minmax(9rem,.7fr)_auto] lg:max-w-5xl">
                                @csrf
                                <div>
                                    <label for="storage-{{ $case->id }}" class="sr-only">Mortuary storage location</label>
                                    <input id="storage-{{ $case->id }}" name="storage_location" required maxlength="120" placeholder="Storage location" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                    @error('storage_location')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="tag-{{ $case->id }}" class="sr-only">Body tag number</label>
                                    <input id="tag-{{ $case->id }}" name="body_tag_number" maxlength="80" placeholder="Body tag (optional)" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                    @error('body_tag_number')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="handoff-note-{{ $case->id }}" class="sr-only">Handoff note</label>
                                    <input id="handoff-note-{{ $case->id }}" name="handoff_note" maxlength="2000" placeholder="Handoff note (optional)" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                </div>
                                <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Confirm received</button>
                            </form>
                        @else
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">Awaiting mortuary receipt</span>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No A&amp;E cases are awaiting mortuary receipt.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900">Confirmed BID files</h2>
                <p class="mt-1 text-sm text-slate-500">Review the doctor-confirmed hospital death record and issue or download its certificate.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($broughtInDeadCases as $case)
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $case->patient->full_name }} · <span class="font-mono text-sm">{{ $case->case_number }}</span></p>
                            <p class="mt-1 text-xs text-slate-500">{{ $case->patient->hospital_number }} · Death confirmed {{ $case->declared_at->format('d M Y, g:i A') }} by {{ $case->declaredBy?->name ?? 'A&amp;E doctor' }}</p>
                            <p class="mt-1 text-sm text-slate-700">Disposition: {{ $case->release_route === 'direct_family_release' ? 'Direct family release' : ($case->status === 'received' ? 'In mortuary custody' : 'Awaiting mortuary receipt') }}</p>
                        </div>
                        <div class="w-full lg:max-w-md">
                            @if ($case->death_certificate_issued_at)
                                <p class="text-sm font-medium text-teal-800">Certificate {{ $case->death_certificate_number }} · issued {{ $case->death_certificate_issued_at->format('d M Y, g:i A') }}</p>
                                @if (auth()->user()->hasRole('admin', 'doctor'))<a href="{{ route('emergency.brought-in-dead.certificate.download', $case->visit) }}" class="mt-2 inline-flex rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">Download death certificate PDF</a>@else<span class="mt-2 block text-xs text-slate-500">Certificate is on file.</span>@endif
                            @elseif (auth()->user()->hasRole('doctor') && auth()->user()->canAccessEmergency())
                                <details>
                                    <summary class="cursor-pointer font-semibold text-teal-700 hover:underline">Issue hospital death certificate</summary>
                                    <form method="POST" action="{{ route('emergency.brought-in-dead.certificate.issue', $case->visit) }}" class="mt-3 space-y-3 rounded-lg border border-teal-200 bg-teal-50 p-4">
                                        @csrf
                                        <label class="block text-xs font-medium text-slate-700">Professional registration number <input name="registration_number" value="{{ old('registration_number') }}" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                                        <label class="block text-xs font-medium text-slate-700">Certified cause of death <textarea name="certified_cause" required minlength="2" maxlength="5000" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('certified_cause', $case->cause_of_death) }}</textarea></label>
                                        <label class="block text-xs font-medium text-slate-700">Other significant conditions <textarea name="other_conditions" maxlength="5000" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('other_conditions') }}</textarea></label>
                                        <button class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">Issue and download certificate</button>
                                    </form>
                                </details>
                            @else
                                <span class="text-sm text-slate-500">Certificate not yet issued.</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No confirmed BID files yet.</p>
                @endforelse
            </div>
        </section>
    </div>
    <script>
        document.querySelectorAll('[data-bid-disposition]').forEach((select) => {
            const form = select.closest('form');
            const fields = form.querySelector('[data-bid-family-fields]');
            const requiredFields = fields.querySelectorAll('[name="released_to"], [name="recipient_relationship"], [name="release_authority"]');
            const sync = () => {
                const direct = select.value === 'direct_family_release';
                fields.classList.toggle('hidden', !direct);
                requiredFields.forEach((input) => { input.required = direct; });
            };
            select.addEventListener('change', sync);
            sync();
        });
    </script>
</x-app-layout>
