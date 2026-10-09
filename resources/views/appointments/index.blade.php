<x-app-layout>
    <x-slot name="header">Appointments</x-slot>

    @php $frontDesk = auth()->user()->hasRole('admin', 'receptionist'); @endphp

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-2">
            <a href="{{ route('appointments.index', ['date' => $date->copy()->subDay()->toDateString()]) }}"
               class="rounded-lg border bg-white px-3 py-2 text-sm hover:bg-slate-50">‹</a>

            <form method="GET">
                <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()"
                       class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
            </form>

            <a href="{{ route('appointments.index', ['date' => $date->copy()->addDay()->toDateString()]) }}"
               class="rounded-lg border bg-white px-3 py-2 text-sm hover:bg-slate-50">›</a>

            @unless ($date->isToday())
                <a href="{{ route('appointments.index') }}" class="text-sm text-teal-700 hover:underline">Today</a>
            @endunless
        </div>

        @if ($frontDesk)
            <a href="{{ route('appointments.create') }}"
               class="shrink-0 whitespace-nowrap rounded-lg bg-teal-700 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-teal-800">
                + Book appointment
            </a>
        @endif
    </div>

    <p class="mb-3 text-sm text-slate-500">{{ $date->format('l, d F Y') }} · {{ $appointments->count() }} appointment(s)</p>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 whitespace-nowrap text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Doctor</th>
                    <th class="px-4 py-3">Reason</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($appointments as $appt)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $appt->scheduled_at->format('g:i A') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('patients.show', $appt->patient) }}" class="font-medium text-teal-800 hover:underline">
                                {{ $appt->patient->full_name }}
                            </a>
                            <span class="block font-mono text-xs text-slate-400">{{ $appt->patient->hospital_number }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $appt->doctor->name }}</td>
                        <td class="px-4 py-3">{{ $appt->reason ?: '—' }}</td>
                        <td class="px-4 py-3 align-middle">
                            <x-badge :color="$appt->status->color()" class="whitespace-nowrap font-semibold">{{ $appt->status->label() }}</x-badge>
                        </td>
                        <td class="px-4 py-3 text-right align-middle">
                            @if ($frontDesk && $appt->status->value === 'scheduled')
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('appointments.check-in', $appt) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Check in</button>
                                    </form>
                                    <form method="POST" action="{{ route('appointments.cancel', $appt) }}"
                                          onsubmit="return confirm('Cancel this appointment?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Cancel</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No appointments on this day.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>