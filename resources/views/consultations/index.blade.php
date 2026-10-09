<x-app-layout>
    <x-slot name="header">Consultation Queue</x-slot>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Complaint</th>
                    <th class="px-4 py-3">Vitals</th>
                    <th class="px-4 py-3">Waiting</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($visits as $visit)
                    @php $v = $visit->vitals; @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $visit->patient->full_name }}</span>
                            <span class="block text-xs text-slate-400">{{ ucfirst($visit->patient->gender) }}, {{ $visit->patient->age }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $visit->complaint ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($v)
                                <span class="text-xs text-slate-600">
                                    {{ $v->temperature }}°C · P {{ $v->pulse }} · BP {{ $v->blood_pressure ?? '—' }}
                                </span>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($v->alerts() as $alert)
                                        <x-badge color="red">{{ $alert }}</x-badge>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $visit->checked_in_at->diffForHumans(short: true) }}</td>
                        <td class="px-4 py-3"><x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge></td>
                        <td class="px-4 py-3 text-right">
                            @if ($visit->status->value === 'waiting_doctor')
                                <form method="POST" action="{{ route('consultations.start', $visit) }}">
                                    @csrf
                                    <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">
                                        Start consultation
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('consultations.show', $visit) }}"
                                   class="rounded-lg border border-teal-700 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">
                                    Continue
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No patients waiting.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>