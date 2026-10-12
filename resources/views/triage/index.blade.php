<x-app-layout>
    <x-slot name="header">Triage</x-slot>

    <h2 class="mb-3 font-semibold">Waiting for vitals ({{ $waiting->count() }})</h2>
    <div class="mb-10 overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Assigned doctor</th>
                    <th class="px-4 py-3">Complaint</th>
                    <th class="px-4 py-3">Waiting</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($waiting as $visit)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $visit->patient->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-400">{{ $visit->patient->hospital_number }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $visit->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $visit->doctor?->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3">{{ $visit->complaint ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $visit->checked_in_at->diffForHumans(short: true) }}</td>
                       
                        <td class="px-4 py-3 text-right">
                            @if ($visit->consultation_paid)
                                <a href="{{ route('triage.show', $visit) }}"
                                class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Take vitals</a>
                            @else
                                <x-badge color="amber">Awaiting payment</x-badge>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Nobody in your department is waiting for triage.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 font-semibold">Sent to doctor today ({{ $done->count() }})</h2>
    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <tbody class="divide-y divide-slate-100">
                <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Assigned doctor</th><th class="px-4 py-3">Vitals alerts</th><th class="px-4 py-3"></th></tr>
                @forelse ($done as $visit)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $visit->patient->full_name }}</td>
                        <td class="px-4 py-3">{{ $visit->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $visit->doctor?->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3">
                            @foreach ($visit->vitals?->alerts() ?? [] as $alert)
                                <x-badge color="red">{{ $alert }}</x-badge>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('triage.show', $visit) }}" class="text-teal-700 hover:underline">Edit vitals</a>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-slate-500">None yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
