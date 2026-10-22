<x-app-layout>
    <x-slot name="header">Medication administration</x-slot>

    <p class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        This queue shows medicines linked to consultations and admissions. Pharmacy must dispense each medicine before a nurse records it; active A&E medicines can be dispensed and administered before billing.
    </p>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Assigned doctor</th><th class="px-4 py-3">Injections</th><th class="px-4 py-3">Care started</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($visits as $visit)
                    @php
                        $remaining = $visit->prescriptions->sum(fn ($rx) => $rx->administration_waived_at ? 0 : max(0, $rx->required_administration_count - $rx->administered_count));
                        $notDispensed = $visit->prescriptions->filter(fn ($rx) => ! $rx->administration_waived_at && $rx->administered_count < $rx->required_administration_count && $rx->status->value !== 'completed')->count();
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $visit->patient->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-400">{{ $visit->patient->hospital_number }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $visit->admission?->department?->name ?? $visit->department?->name ?? '—' }}@if ($visit->status->value === 'inpatient')<span class="mt-1 block"><x-badge color="teal">Inpatient</x-badge></span>@endif</td>
                        <td class="px-4 py-3">{{ $visit->doctor?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span>{{ $remaining }} dose(s) remain</span>
                            @if ($notDispensed)<span class="block text-xs text-amber-700">{{ $notDispensed }} prescription(s) awaiting pharmacy</span>@endif
                            @if ($visit->is_emergency)<span class="mt-1 block"><x-badge color="red">Active A&E</x-badge></span>@endif
                        </td>
                        <td class="px-4 py-3">{{ ($visit->closed_at ?? $visit->checked_in_at)?->format('d M Y, g:i A') }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('nursing.administrations.show', $visit) }}" class="font-medium text-teal-700 hover:underline">Open care record →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No hospital medication administrations are waiting for your department.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
