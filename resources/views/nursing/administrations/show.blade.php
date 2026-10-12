<x-app-layout>
    <x-slot name="header">Nursing care · {{ $visit->patient->full_name }}</x-slot>

    @php
        $incomplete = $visit->prescriptions->filter(fn ($rx) => ! $rx->administration_waived_at && ($rx->status->value !== 'completed' || $rx->administered_count < $rx->required_administration_count));
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-5 shadow-sm">
        <div>
            <p class="font-medium">{{ $visit->patient->full_name }} <span class="font-mono text-sm text-slate-500">{{ $visit->patient->hospital_number }}</span></p>
            <p class="mt-1 text-sm text-slate-500">Department: {{ $visit->department?->name ?? '—' }} · Doctor: {{ $visit->doctor?->name ?? '—' }} · Consultation completed {{ $visit->closed_at?->format('d M Y, g:i A') }}</p>
        </div>
        <a href="{{ route('nursing.administrations.index') }}" class="text-sm text-teal-700 hover:underline">← Nursing queue</a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @forelse ($visit->prescriptions as $rx)
        <section class="mb-5 rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ $rx->drug->display_name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $rx->dosage }} · {{ $rx->frequency }} · {{ $rx->duration }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $rx->administered_count }} of {{ $rx->required_administration_count }} administrations recorded</p>
                </div>
                @if ($rx->administration_waived_at)
                    <x-badge color="slate">Waived by doctor/admin</x-badge>
                @elseif ($rx->status->value === 'completed' && $rx->stockReservation?->status === 'fulfilled')
                    <x-badge color="green">Dispensed · ready for nursing</x-badge>
                @else
                    <x-badge color="amber">Waiting for pharmacy</x-badge>
                @endif
            </div>

            @if ($rx->administration_waived_at)
                <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">Waiver reason: {{ $rx->administration_waiver_reason }}</p>
            @endif

            @if ($rx->administrations->isNotEmpty())
                <div class="mt-4 border-t pt-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Administration history</h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($rx->administrations as $record)
                            <li class="rounded-lg bg-slate-50 px-3 py-2">
                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $record->status)) }}</span>
                                @if ($record->administered_at) · {{ $record->administered_at->format('d M Y, g:i A') }} @endif
                                @if ($record->nurse) · {{ $record->nurse->name }} @endif
                                @if ($record->dose) · Dose: {{ $record->dose }} @endif
                                @if ($record->route) · Route: {{ $record->route }} @endif
                                @if ($record->site) · Site: {{ $record->site }} @endif
                                @if ($record->reason)<span class="mt-1 block text-xs text-slate-600">Reason: {{ $record->reason }}</span>@endif
                                @if ($record->notes)<span class="mt-1 block text-xs text-slate-600">Notes: {{ $record->notes }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $rx->administration_waived_at && $rx->status->value === 'completed' && $rx->stockReservation?->status === 'fulfilled' && $rx->administered_count < $rx->required_administration_count)
                <form method="POST" action="{{ route('nursing.administrations.store', [$visit, $rx]) }}" class="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Outcome</label>
                        <select name="status" required class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="administered">Administered</option>
                            <option value="refused">Patient refused</option>
                            <option value="deferred">Deferred</option>
                            <option value="not_given">Not given</option>
                        </select>
                    </div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Dose given</label><input name="dose" value="{{ $rx->dosage }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Route</label><input name="route" placeholder="e.g. IM, IV, SC" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Site</label><input name="site" placeholder="e.g. left deltoid" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Reason if not given</label><input name="reason" maxlength="2000" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-600">Notes</label><input name="notes" maxlength="2000" class="w-full rounded-lg border-slate-300 text-sm"></div>
                    <div class="sm:col-span-2"><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Record outcome</button></div>
                </form>
            @endif
        </section>
    @empty
        <p class="rounded-xl border bg-white p-8 text-center text-slate-500">No hospital-administered medicines are recorded for this visit.</p>
    @endforelse

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        @if ($visit->discharged_at)
            <p class="text-sm text-green-800">Discharged {{ $visit->discharged_at->format('d M Y, g:i A') }} by {{ $visit->dischargeActor?->name ?? 'staff' }}.</p>
        @elseif ($incomplete->isEmpty())
            <p class="mb-3 text-sm text-slate-600">All required nursing administrations are complete or have a documented doctor/admin waiver.</p>
            <form method="POST" action="{{ route('nursing.administrations.discharge', $visit) }}">
                @csrf
                <button class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">Complete nursing care and discharge</button>
            </form>
        @else
            <p class="text-sm text-amber-800">Discharge is blocked while required injections are awaiting pharmacy or nursing action. If an order will not be given, the attending doctor must review and waive it with a reason.</p>
        @endif
    </section>
</x-app-layout>
