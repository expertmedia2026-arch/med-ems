<x-app-layout>
    <x-slot name="header">Mortuary case · {{ $case->case_number }}</x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('mortuary.index') }}" class="text-sm font-medium text-teal-700 hover:underline">← Back to mortuary register</a>

        <section class="overflow-hidden rounded-xl border border-teal-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4 bg-teal-900 px-5 py-5 text-white sm:px-7">
                <div><p class="font-mono text-sm text-teal-100">{{ $case->case_number }} · {{ $case->patient->hospital_number }}</p><h1 class="mt-1 text-2xl font-semibold">{{ $case->patient->full_name }}</h1><p class="mt-1 text-sm text-teal-100">Deceased {{ $case->deceased_at->format('d M Y, g:i A') }}</p></div>
                <div class="rounded-lg border border-white/20 bg-white/10 px-4 py-3 text-sm"><p class="text-xs text-teal-100">Case status</p><p class="mt-1 font-semibold">{{ $case->status === 'released' && $case->release_route === 'direct_family_release' ? 'Released directly to family' : (['awaiting_transfer' => 'Awaiting transfer', 'received' => 'In mortuary', 'released' => 'Released'][$case->status] ?? ucfirst($case->status)) }}</p><p class="mt-1 text-xs text-teal-100">Body tag: {{ $case->body_tag_number ?: 'Not assigned' }}</p></div>
            </div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Source</dt><dd class="mt-1 text-slate-800">{{ $case->source === 'walk_in' ? 'External walk-in' : ($case->visit?->admission ? 'Hospital inpatient' : ($case->visit?->is_emergency ? 'Hospital A&E' : 'Hospital')) }}</dd></div>
                    @if ($case->visit)
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Presenting complaint / clinical handoff</dt><dd class="mt-1 whitespace-pre-line text-slate-800">{{ $case->visit->complaint ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Attending doctor</dt><dd class="mt-1 text-slate-800">{{ $case->visit->doctor?->name ?? $case->openedBy?->name ?? '—' }}</dd></div>
                        @if ($case->visit->admission)<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Ward</dt><dd class="mt-1 text-slate-800">{{ $case->visit->admission->department?->name ?? '—' }}</dd></div>@endif
                        @if ($case->declared_at)<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Death recorded by</dt><dd class="mt-1 text-slate-800">{{ $case->declaredBy?->name ?? '—' }} · {{ $case->declared_at->format('d M Y, g:i A') }}</dd></div>@endif
                        @if ($case->cause_of_death)<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Cause / clinical circumstances</dt><dd class="mt-1 whitespace-pre-line text-slate-800">{{ $case->cause_of_death }}</dd></div>@endif
                    @else
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Approximate age</dt><dd class="mt-1 text-slate-800">{{ $case->deceased_age !== null ? $case->deceased_age.' years' : 'Not recorded' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Depositor / contact</dt><dd class="mt-1 text-slate-800">{{ $case->external_contact_name ?: 'Not recorded' }} · {{ $case->external_contact_phone ?: 'No phone' }}</dd></div>
                        @if ($case->external_contact_address)<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contact address</dt><dd class="mt-1 text-slate-800">{{ $case->external_contact_address }}</dd></div>@endif
                    @endif
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Clinical summary / handoff note</dt><dd class="mt-1 whitespace-pre-line text-slate-800">{{ $case->death_clinical_summary ?: $case->handoff_note ?: 'No additional handoff note.' }}</dd></div>
                </dl>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Next of kin</dt><dd class="mt-1 text-slate-800">{{ $case->patient->next_of_kin_name ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Next of kin phone</dt><dd class="mt-1 text-slate-800">{{ $case->patient->next_of_kin_phone ?: 'Not recorded' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current storage location</dt><dd class="mt-1 font-semibold text-slate-900">{{ $case->storage_location ?: 'Not received' }}</dd></div>
                </dl>
            </div>
        </section>

        @if ($case->invoice)
            <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                    <div><h2 class="font-semibold text-slate-900">Linked invoice</h2><p class="mt-1 text-sm text-slate-500">@if ($case->release_route === 'direct_family_release') Direct family release was recorded without opening mortuary custody charges. @else The intake fee is due before receipt. Storage and State tax accrue per started 24-hour period and must be paid before release. @endif The total and balance can include other hospital visit charges.</p></div>
                    <div class="text-right"><p class="font-mono text-sm text-teal-700">{{ $case->invoice->invoice_number }}</p><p class="mt-1 text-sm font-semibold text-slate-900">Total ₦{{ number_format($case->invoice->total, 2) }} · Balance ₦{{ number_format($case->invoice->balance, 2) }}</p></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Charge</th><th class="px-5 py-3 text-right">Qty</th><th class="px-5 py-3 text-right">Unit fee</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @forelse ($case->invoice->items->where('category', 'mortuary') as $item)
                            <tr><td class="px-5 py-3">{{ $item->description }}</td><td class="px-5 py-3 text-right">{{ $item->quantity }}</td><td class="px-5 py-3 text-right">₦{{ number_format($item->unit_price, 2) }}</td><td class="px-5 py-3 text-right">₦{{ number_format($item->total, 2) }}</td><td class="px-5 py-3">{{ $item->is_paid ? 'Paid' : 'Awaiting payment' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-5 text-center text-slate-500">No mortuary charges have been added.</td></tr>
                        @endforelse
                    </tbody></table>
                </div>
                @if (auth()->user()->hasRole('admin', 'accountant', 'receptionist'))
                    <div class="border-t px-5 py-3 text-right"><a href="{{ route('billing.show', $case->invoice) }}" class="text-sm font-semibold text-teal-700 hover:underline">Open linked invoice and collect payment →</a></div>
                @endif
            </section>
        @endif

        @if ($case->status === 'awaiting_transfer')
            <section class="rounded-xl border border-teal-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="font-semibold text-slate-900">Receive into mortuary custody</h2>
                <p class="mt-1 text-sm text-slate-500">Confirm physical receipt, record the assigned storage location, and enter the body tag if available. Intake fee: ₦{{ number_format((float) $case->intake_fee, 2) }}.</p>
                @unless ($intakeFeePaid)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">The intake fee must be paid on the linked invoice before the body can be accepted into mortuary custody.</div>
                @endunless
                <form method="POST" action="{{ route('mortuary.receive', $case) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div><label class="block text-sm font-medium text-slate-700" for="storage_location">Storage location <span class="text-red-600">*</span></label><input id="storage_location" name="storage_location" value="{{ old('storage_location') }}" required maxlength="120" placeholder="e.g. Cold room 1 · shelf B" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('storage_location')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="body_tag_number">Body tag number</label><input id="body_tag_number" name="body_tag_number" value="{{ old('body_tag_number') }}" maxlength="80" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('body_tag_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700" for="handoff_note">Receipt / handoff note</label><textarea id="handoff_note" name="handoff_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('handoff_note') }}</textarea></div>
                    <div class="sm:col-span-2"><button @disabled(! $intakeFeePaid) class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">Confirm mortuary receipt</button></div>
                </form>
            </section>
        @elseif ($case->status === 'received')
            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="font-semibold text-slate-900">Move within storage</h2>
                    <p class="mt-1 text-sm text-slate-500">Every location change is added to the custody history.</p>
                    <form method="POST" action="{{ route('mortuary.move', $case) }}" class="mt-4 space-y-3">
                        @csrf
                        <div><label class="block text-sm font-medium text-slate-700" for="new_storage_location">New location</label><input id="new_storage_location" name="storage_location" required maxlength="120" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700" for="move_note">Reason / note</label><textarea id="move_note" name="note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></div>
                        <button class="rounded-lg border border-teal-700 px-4 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50">Record location change</button>
                    </form>
                </section>

                <section class="rounded-xl border border-teal-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="font-semibold text-slate-900">Release body</h2>
                    <p class="mt-1 text-sm text-slate-500">Record the recipient and authority for release. Intake, storage, and State tax charges must be fully paid before release.</p>
                    @unless ($mortuaryChargesPaid)
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">There are unpaid mortuary charges on the linked invoice. The release action will be available after payment is recorded.</div>
                    @endunless
                    <form method="POST" action="{{ route('mortuary.release', $case) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div><label class="block text-sm font-medium text-slate-700" for="released_to">Recipient name <span class="text-red-600">*</span></label><input id="released_to" name="released_to" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('released_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="block text-sm font-medium text-slate-700" for="recipient_relationship">Relationship <span class="text-red-600">*</span></label><input id="recipient_relationship" name="recipient_relationship" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700" for="recipient_id_reference">Recipient ID / reference</label><input id="recipient_id_reference" name="recipient_id_reference" maxlength="120" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700" for="release_authority">Release authority / document reference <span class="text-red-600">*</span></label><input id="release_authority" name="release_authority" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700" for="release_note">Release note</label><textarea id="release_note" name="release_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></div>
                        <div class="sm:col-span-2"><button @disabled(! $mortuaryChargesPaid) class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">Record release and close case</button></div>
                    </form>
                </section>
            </div>
        @else
            <section class="rounded-xl border border-green-200 bg-green-50 p-5 text-sm text-green-950">
                <h2 class="font-semibold">{{ $case->release_route === 'direct_family_release' ? 'Released directly to family / authorized representative' : 'Case released from mortuary' }}</h2>
                <p class="mt-1">Released to {{ $case->released_to }} ({{ $case->recipient_relationship }}) on {{ $case->released_at?->format('d M Y, g:i A') }} by {{ $case->releasedBy?->name ?? 'staff' }}.</p>
                <p class="mt-1">Authority: {{ $case->release_authority }}@if ($case->recipient_id_reference) · Recipient ID/reference: {{ $case->recipient_id_reference }}@endif</p>
                @if ($case->release_note)<p class="mt-2 whitespace-pre-line">{{ $case->release_note }}</p>@endif
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="border-b px-5 py-4 sm:px-6"><h2 class="font-semibold text-slate-900">Custody history</h2><p class="mt-1 text-sm text-slate-500">Recorded actions, locations, and staff members.</p></div>
            <ol class="divide-y divide-slate-100">
                @forelse ($case->events as $event)
                    <li class="flex gap-4 p-5 sm:px-6"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-teal-700"></span><div class="min-w-0 flex-1"><div class="flex flex-wrap justify-between gap-2"><p class="font-medium text-slate-900">{{ $event->event_type === 'released' && ($event->details['release_route'] ?? null) === 'direct_family_release' ? 'Released directly to family / representative' : (['case_opened' => 'Case opened', 'received' => 'Received into mortuary', 'location_changed' => 'Storage location changed', 'released' => 'Released from mortuary'][$event->event_type] ?? ucfirst(str_replace('_', ' ', $event->event_type))) }} · {{ $event->actor?->name ?? 'Staff' }}</p><time class="text-xs text-slate-500">{{ $event->occurred_at->format('d M Y, g:i A') }}</time></div>
                        @if ($event->event_type === 'location_changed')<p class="mt-1 text-sm text-slate-600">{{ $event->details['from'] ?? '—' }} → {{ $event->details['to'] ?? '—' }}</p>@elseif ($event->details['storage_location'] ?? null)<p class="mt-1 text-sm text-slate-600">Location: {{ $event->details['storage_location'] }}</p>@endif
                        @if ($event->details['released_to'] ?? null)<p class="mt-1 text-sm text-slate-600">To {{ $event->details['released_to'] }} ({{ $event->details['relationship'] ?? 'relationship not recorded' }}) · Authority: {{ $event->details['authority'] ?? '—' }}</p>@endif
                        @if ($event->details['note'] ?? null)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $event->details['note'] }}</p>@endif
                    </div></li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No custody events recorded.</li>
                @endforelse
            </ol>
        </section>
    </div>
</x-app-layout>
