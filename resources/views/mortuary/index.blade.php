<x-app-layout>
    <x-slot name="header">Mortuary</x-slot>

    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Mortuary</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Case register</h1>
            <p class="mt-1 text-sm text-slate-500">Receive deceased patients from clinical teams, track storage, and document authorized release.</p>
        </div>

        <form method="GET" action="{{ route('mortuary.index') }}" class="grid gap-3 rounded-xl border bg-white p-4 shadow-sm sm:grid-cols-[1fr_14rem_auto]">
            <label class="sr-only" for="mortuary-search">Search cases</label>
            <input id="mortuary-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search case number, tag, patient, hospital number" class="rounded-lg border-slate-300 text-sm shadow-sm">
            <label class="sr-only" for="mortuary-status">Filter by status</label>
            <select id="mortuary-status" name="status" class="rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All case statuses</option>
                @foreach (['awaiting_transfer' => 'Awaiting transfer', 'received' => 'In mortuary', 'released' => 'Released'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Search</button>
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
                                <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $case->patient->full_name }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $case->patient->hospital_number }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $case->deceased_at->format('d M Y, g:i A') }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $case->storage_location ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @php($statusColor = $case->status === 'released' ? 'green' : ($case->status === 'received' ? 'teal' : 'amber'))
                                    <x-badge :color="$statusColor">{{ ['awaiting_transfer' => 'Awaiting transfer', 'received' => 'In mortuary', 'released' => 'Released'][$case->status] ?? ucfirst($case->status) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('mortuary.show', $case) }}" class="font-semibold text-amber-800 hover:underline">Open case</a></td>
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
