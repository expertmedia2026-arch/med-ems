<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $tones = [
            'blue'  => 'text-blue-700',
            'amber' => 'text-amber-600',
            'green' => 'text-green-700',
            'red'   => 'text-red-700',
        ];
    @endphp

    <div class="mb-6">
        <h2 class="text-2xl font-semibold">Welcome, {{ auth()->user()->name }}</h2>
        <p class="text-slate-500">{{ auth()->user()->role->label() }}@if (auth()->user()->department) · {{ auth()->user()->department->name }} Department @endif · {{ now()->format('l, d F Y') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as $card)
            @if ($card['url'])
                <a href="{{ $card['url'] }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-teal-600 hover:shadow">
            @else
                <div class="rounded-xl border bg-white p-5 shadow-sm">
            @endif

                <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold {{ $tones[$card['color']] ?? 'text-teal-800' }}">{{ $card['value'] }}</p>

            @if ($card['url'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    @if ($queue->isNotEmpty() || auth()->user()->hasRole('admin', 'receptionist', 'nurse', 'doctor'))
        <div class="mt-8 rounded-xl border bg-white shadow-sm">
            <h3 class="border-b px-5 py-3 font-semibold">Live queue</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Patient</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Complaint</th>
                            <th class="px-4 py-3">Doctor</th>
                            <th class="px-4 py-3">Waiting</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($queue as $visit)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $visit->patient->full_name }}</span>
                                    <span class="block font-mono text-xs text-slate-400">{{ $visit->patient->hospital_number }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $visit->department?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $visit->complaint ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $visit->doctor?->name ?? 'Unassigned' }}</td>
                                <td class="px-4 py-3">{{ $visit->checked_in_at->diffForHumans(short: true) }}</td>
                                <td class="px-4 py-3"><x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nobody in the queue right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
