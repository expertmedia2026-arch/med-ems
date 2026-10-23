<x-app-layout>
    <x-slot name="header">Mortuary</x-slot>

    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Mortuary</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Case register</h1>
            <p class="mt-1 text-sm text-slate-500">Manage hospital transfers and external walk-ins, track storage, and document authorized release.</p>
        </div>

        <details class="overflow-hidden rounded-xl border border-teal-200 bg-white shadow-sm" {{ $errors->any() ? 'open' : '' }}>
            <summary class="cursor-pointer bg-teal-50 px-5 py-4 font-semibold text-teal-950">Register a walk-in body</summary>
            <div class="border-t p-5">
                <p class="mb-4 text-sm text-slate-600">For a deceased person brought from outside the hospital. This opens a mortuary-only case and invoice without registering an OPD patient. The body remains awaiting transfer until the intake fee is paid and a staff member records the physical receipt. Current fees: intake ₦{{ number_format((float) $intakeFee, 2) }}; storage ₦{{ number_format((float) $dailyStorageFee, 2) }} and State tax ₦{{ number_format((float) $stateTaxDailyFee, 2) }} per started 24 hours.</p>
                <form method="POST" action="{{ route('mortuary.walk-in.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    <div><label class="block text-sm font-medium text-slate-700" for="first_name">Deceased first name <span class="text-red-600">*</span></label><input id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="80" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('first_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="last_name">Deceased last name <span class="text-red-600">*</span></label><input id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="80" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('last_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="gender">Sex <span class="text-red-600">*</span></label><select id="gender" name="gender" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">Select</option><option value="male" @selected(old('gender') === 'male')>Male</option><option value="female" @selected(old('gender') === 'female')>Female</option><option value="unknown" @selected(old('gender') === 'unknown')>Unknown</option></select>@error('gender')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="estimated_age">Approximate age</label><input id="estimated_age" name="estimated_age" type="number" min="0" max="120" value="{{ old('estimated_age') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('estimated_age')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="deceased_at">Date and time of death <span class="text-red-600">*</span></label><input id="deceased_at" name="deceased_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('deceased_at') }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('deceased_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="external_contact_name">Depositor / contact name <span class="text-red-600">*</span></label><input id="external_contact_name" name="external_contact_name" value="{{ old('external_contact_name') }}" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('external_contact_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-sm font-medium text-slate-700" for="external_contact_phone">Contact phone <span class="text-red-600">*</span></label><input id="external_contact_phone" name="external_contact_phone" value="{{ old('external_contact_phone') }}" required maxlength="30" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('external_contact_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2 lg:col-span-3"><label class="block text-sm font-medium text-slate-700" for="external_contact_address">Contact address</label><input id="external_contact_address" name="external_contact_address" value="{{ old('external_contact_address') }}" maxlength="1000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">@error('external_contact_address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2 lg:col-span-3"><label class="block text-sm font-medium text-slate-700" for="handoff_note">Intake note</label><textarea id="handoff_note" name="handoff_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('handoff_note') }}</textarea>@error('handoff_note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2 lg:col-span-3"><button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Open case and intake invoice</button></div>
                </form>
            </div>
        </details>

        <form method="GET" action="{{ route('mortuary.index') }}" class="grid gap-3 rounded-xl border bg-white p-4 shadow-sm sm:grid-cols-[1fr_14rem_auto]">
            <label class="sr-only" for="mortuary-search">Search cases</label>
            <input id="mortuary-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search case number, tag, patient, hospital number" class="rounded-lg border-slate-300 text-sm shadow-sm">
            <label class="sr-only" for="mortuary-status">Filter by status</label>
            <select id="mortuary-status" name="status" class="rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All case statuses</option>
                @foreach (['awaiting_transfer' => 'Awaiting transfer', 'received' => 'In mortuary', 'released' => 'Released / direct family release'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Search</button>
        </form>

        <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Case / tag</th><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Deceased</th><th class="px-5 py-3">Storage</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($cases as $case)
                            <tr>
                                <td class="px-5 py-4"><p class="font-mono font-semibold text-slate-900">{{ $case->case_number }}</p><p class="mt-1 text-xs text-slate-500">Tag: {{ $case->body_tag_number ?: 'Not assigned' }}</p></td>
                                <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $case->patient->full_name }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $case->patient->hospital_number }} · {{ $case->source === 'walk_in' ? 'Walk-in' : ($case->visit?->admission ? 'Hospital inpatient' : 'Hospital A&E') }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $case->deceased_at->format('d M Y, g:i A') }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $case->storage_location ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @php($statusColor = $case->status === 'released' ? 'green' : ($case->status === 'received' ? 'teal' : 'amber'))
                                    <x-badge :color="$statusColor">{{ $case->status === 'released' && $case->release_route === 'direct_family_release' ? 'Direct family release' : (['awaiting_transfer' => 'Awaiting transfer', 'received' => 'In mortuary', 'released' => 'Released'][$case->status] ?? ucfirst($case->status)) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('mortuary.show', $case) }}" class="font-semibold text-teal-700 hover:underline">Open case</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No mortuary cases match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cases->hasPages())<div class="border-t px-5 py-3">{{ $cases->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
