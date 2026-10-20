<x-app-layout>
    <x-slot name="header">Admissions</x-slot>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <a href="#active-admissions" class="rounded-xl border border-teal-200 bg-white p-5 shadow-sm hover:border-teal-500">
            <p class="text-sm font-medium text-slate-500">Current inpatients</p>
            <p class="mt-1 text-3xl font-bold text-teal-800">{{ $activeCount }}</p>
        </a>
        <a href="#active-admissions" class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm hover:border-amber-400">
            <p class="text-sm font-medium text-slate-500">Laboratory results to review</p>
            <p class="mt-1 text-3xl font-bold text-amber-700">{{ $pendingReviewCount }}</p>
        </a>
    </div>

    <form method="GET" action="{{ route('admissions.index') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4 shadow-sm">
        <label class="min-w-64 flex-1 text-sm font-medium text-slate-700">Find patient or hospital number
            <input type="search" name="search" value="{{ $search }}" placeholder="Search admissions" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
        </label>
        <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Search</button>
        @if ($search)<a href="{{ route('admissions.index') }}" class="rounded-lg border px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Clear</a>@endif
    </form>

    <section id="active-admissions" class="mb-8 overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-900">Active inpatient care</h2>
                <p class="mt-1 text-sm text-slate-500">Open the linked admission for current plans, laboratory results, and discharge.</p>
            </div>
            <span class="rounded-full bg-teal-50 px-3 py-1 text-sm font-medium text-teal-800">{{ $activeCount }} active</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Patient / admission</th><th class="px-4 py-3">Ward department</th><th class="px-4 py-3">Attending doctor</th><th class="px-4 py-3">Admitted</th><th class="px-4 py-3">Follow-up</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($activeAdmissions as $admission)
                        <tr>
                            <td class="px-4 py-3"><span class="font-medium text-slate-900">{{ $admission->patient->full_name }}</span><span class="mt-0.5 block font-mono text-xs text-slate-500">{{ $admission->admission_number }} · {{ $admission->patient->hospital_number }}</span></td>
                            <td class="px-4 py-3">{{ $admission->department->name }}</td>
                            <td class="px-4 py-3">{{ $admission->attendingDoctor->name }}</td>
                            <td class="px-4 py-3">{{ $admission->admitted_at->format('d M Y, g:i A') }}<span class="block text-xs text-slate-500">Day {{ $admission->admitted_at->diffInDays(now()) + 1 }}</span></td>
                            <td class="px-4 py-3">
                                @if ($admission->pending_result_reviews_count)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800">Results to review</span>
                                @else
                                    <span class="text-slate-400">No new results</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admissions.show', $admission) }}" class="font-medium text-teal-700 hover:underline">Open admission →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No active admissions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-4 py-3">{{ $activeAdmissions->links() }}</div>
    </section>

    <section class="overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="border-b px-5 py-4">
            <h2 class="font-semibold text-slate-900">Recently discharged</h2>
            <p class="mt-1 text-sm text-slate-500">Discharge summaries remain linked to the original consultation.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Patient / admission</th><th class="px-4 py-3">Ward department</th><th class="px-4 py-3">Attending doctor</th><th class="px-4 py-3">Discharged</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($dischargedAdmissions as $admission)
                        <tr>
                            <td class="px-4 py-3"><span class="font-medium text-slate-900">{{ $admission->patient->full_name }}</span><span class="mt-0.5 block font-mono text-xs text-slate-500">{{ $admission->admission_number }} · {{ $admission->patient->hospital_number }}</span></td>
                            <td class="px-4 py-3">{{ $admission->department->name }}</td>
                            <td class="px-4 py-3">{{ $admission->attendingDoctor->name }}</td>
                            <td class="px-4 py-3">{{ $admission->discharged_at?->format('d M Y, g:i A') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admissions.show', $admission) }}" class="font-medium text-teal-700 hover:underline">View discharge →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No discharged admissions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-4 py-3">{{ $dischargedAdmissions->links() }}</div>
    </section>
</x-app-layout>
