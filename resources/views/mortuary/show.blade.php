<x-app-layout>
    <x-slot name="header">Mortuary case · {{ $case->case_number }}</x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('mortuary.index') }}" class="text-sm font-medium text-amber-800 hover:underline">← Back to mortuary register</a>

        <section class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4 bg-gradient-to-r from-amber-900 to-amber-800 px-5 py-5 text-white sm:px-7">
                <div><p class="font-mono text-sm text-amber-100">{{ $case->case_number }} · {{ $case->patient->hospital_number }}</p><h1 class="mt-1 text-2xl font-semibold">{{ $case->patient->full_name }}</h1><p class="mt-1 text-sm text-amber-100">Deceased {{ $case->deceased_at->format('d M Y, g:i A') }}</p></div>
                <div class="rounded-lg border border-white/20 bg-white/10 px-4 py-3 text-sm"><p class="text-xs text-amber-100">Case status</p><p class="mt-1 font-semibold">{{ ['awaiting_transfer' => 'Awaiting transfer', 'received' => 'In mortuary', 'released' => 'Released'][$case->status] ?? ucfirst($case->status) }}</p><p class="mt-1 text-xs text-amber-100">Body tag: {{ $case->body_tag_number ?: 'Not assigned' }}</p></div>
            </div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Presenting complaint / clinical handoff</dt><dd class="mt-1 whitespace-pre-line text-slate-800">{{ $case->visit->complaint ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">ER doctor</dt><dd class="mt-1 text-slate-800">{{ $case->visit->doctor?->name ?? $case->openedBy?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">ER handoff note</dt><dd class="mt-1 whitespace-pre-line text-slate-800">{{ $case->handoff_note ?: 'No additional handoff note.' }}</dd></div>
                </dl>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Next of kin</dt><dd class="mt-1 text-slate-800">{{ $case->patient->next_of_kin_name ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Next of kin phone</dt><dd class="mt-1 text-slate-800">{{ $case->patient->next_of_kin_phone ?: 'Not recorded' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Current storage location</dt><dd class="mt-1 font-semibold text-slate-900">{{ $case->storage_location ?: 'Not received' }}</dd></div>
                </dl>
            </div>
        </section>

        @if ($case->status === 'awaiting_transfer')
            <section class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="font-semibold text-slate-900">Receive into mortuary custody</h2>
                <p class="mt-1 text-sm text-slate-500">Confirm physical receipt, record the assigned storage location, and enter the body tag if available.</p>
                <form method="POST" action="{{ route('mortuary.receive', $case) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div><label class="block text-sm font-medium text-slate-700" for="storage_location">Storage location <span class="text-red-600">*</span></label><input id="storage_location" name="storage_location" value="{{ old('storage_location') }}" required maxlength="120" placeholder="e.g. Cold room 1 · shelf B" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('storage_location')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="body_tag_number">Body tag number</label><input id="body_tag_number" name="body_tag_number" value="{{ old('body_tag_number') }}" maxlength="80" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('body_tag_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700" for="handoff_note">Receipt / handoff note</label><textarea id="handoff_note" name="handoff_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('handoff_note') }}</textarea></div>
                    <div class="sm:col-span-2"><button class="rounded-lg bg-amber-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-900">Confirm mortuary receipt</button></div>
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
                        <button class="rounded-lg border border-amber-800 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">Record location change</button>
                    </form>
                </section>

                <section class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="font-semibold text-slate-900">Release body</h2>
                    <p class="mt-1 text-sm text-slate-500">Record the recipient and the authority for release. This closes the mortuary case.</p>
                    <form method="POST" action="{{ route('mortuary.release', $case) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div><label class="block text-sm font-medium text-slate-700" for="released_to">Recipient name <span class="text-red-600">*</span></label><input id="released_to" name="released_to" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('released_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="block text-sm font-medium text-slate-700" for="recipient_relationship">Relationship <span class="text-red-600">*</span></label><input id="recipient_relationship" name="recipient_relationship" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700" for="recipient_id_reference">Recipient ID / reference</label><input id="recipient_id_reference" name="recipient_id_reference" maxlength="120" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700" for="release_authority">Release authority / document reference <span class="text-red-600">*</span></label><input id="release_authority" name="release_authority" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700" for="release_note">Release note</label><textarea id="release_note" name="release_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></div>
                        <div class="sm:col-span-2"><button class="rounded-lg bg-rose-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900">Record release and close case</button></div>
                    </form>
                </section>
            </div>
        @else
            <section class="rounded-xl border border-green-200 bg-green-50 p-5 text-sm text-green-950">
                <h2 class="font-semibold">Case released</h2>
                <p class="mt-1">Released to {{ $case->released_to }} ({{ $case->recipient_relationship }}) on {{ $case->released_at?->format('d M Y, g:i A') }} by {{ $case->releasedBy?->name ?? 'staff' }}.</p>
                <p class="mt-1">Authority: {{ $case->release_authority }}@if ($case->recipient_id_reference) · Recipient ID/reference: {{ $case->recipient_id_reference }}@endif</p>
                @if ($case->release_note)<p class="mt-2 whitespace-pre-line">{{ $case->release_note }}</p>@endif
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="border-b px-5 py-4 sm:px-6"><h2 class="font-semibold text-slate-900">Custody history</h2><p class="mt-1 text-sm text-slate-500">Recorded actions, locations, and staff members.</p></div>
            <ol class="divide-y divide-slate-100">
                @forelse ($case->events as $event)
                    <li class="flex gap-4 p-5 sm:px-6"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-amber-700"></span><div class="min-w-0 flex-1"><div class="flex flex-wrap justify-between gap-2"><p class="font-medium text-slate-900">{{ ['case_opened' => 'Case opened', 'received' => 'Received into mortuary', 'location_changed' => 'Storage location changed', 'released' => 'Released from mortuary'][$event->event_type] ?? ucfirst(str_replace('_', ' ', $event->event_type)) }} · {{ $event->actor?->name ?? 'Staff' }}</p><time class="text-xs text-slate-500">{{ $event->occurred_at->format('d M Y, g:i A') }}</time></div>
                        @if ($event->event_type === 'location_changed')<p class="mt-1 text-sm text-slate-600">{{ $event->details['from'] ?? '—' }} → {{ $event->details['to'] ?? '—' }}</p>@elseif ($event->details['storage_location'] ?? null)<p class="mt-1 text-sm text-slate-600">Location: {{ $event->details['storage_location'] }}</p>@endif
                        @if ($event->details['note'] ?? null)<p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $event->details['note'] }}</p>@endif
                    </div></li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No custody events recorded.</li>
                @endforelse
            </ol>
        </section>
    </div>
</x-app-layout>
