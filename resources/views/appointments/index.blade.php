<x-app-layout>
    <x-slot name="header">Appointments</x-slot>

    @php $frontDesk = auth()->user()->hasRole('admin', 'receptionist'); @endphp

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
               class="rounded-lg bg-teal-700 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-teal-800">
                + Book appointment
            </a>
        @endif
    </div>

    <p class="mb-3 text-sm text-slate-500">{{ $date->format('l, d F Y') }} · {{ $appointments->count() }} appointment(s)</p>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Schedule</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Doctor / Service</th>
                    <th class="px-4 py-3">Reason / Tests</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($appointments as $appt)
                    <tr>
                        <td class="px-4 py-3 font-medium">
                            @if ($appt->isLab())
                                {{ $appt->scheduled_at->format('g:i A') }}
                            @else
                                <span class="text-slate-600">Date only</span>
                                <span class="block text-xs font-normal text-slate-400">Served in arrival order</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('patients.show', $appt->patient) }}" class="font-medium text-teal-800 hover:underline">
                                {{ $appt->patient->full_name }}
                            </a>
                            <span class="block font-mono text-xs text-slate-400">{{ $appt->patient->hospital_number }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($appt->isLab())
                                <x-badge color="blue">Lab test</x-badge>
                            @else
                                {{ $appt->doctor?->name }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($appt->isLab())
                                {{ $appt->labTests->pluck('name')->join(', ') ?: '—' }}
                                @if ($appt->reason)<span class="block text-xs text-slate-400">{{ $appt->reason }}</span>@endif
                            @else
                                {{ $appt->reason ?: '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-badge :color="$appt->status->color()">{{ $appt->status->label() }}</x-badge></td>
                        <td class="px-4 py-3">
                            @if ($frontDesk && $appt->status->value === 'scheduled')
                                <div class="flex justify-end gap-3">
                                    @php($departmentTypes = $consultationTypesByDepartment->get($appt->doctor?->department_id, collect()))
                                    @if ($appt->isLab())
                                        <form method="POST" action="{{ route('appointments.check-in', $appt) }}">
                                            @csrf
                                            <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Check in</button>
                                        </form>
                                    @elseif ($departmentTypes->isNotEmpty())
                                        <form method="POST" action="{{ route('appointments.check-in', $appt) }}" class="flex items-center gap-2">
                                            @csrf
                                            <label class="sr-only" for="consultation_type_{{ $appt->id }}">Consultation type</label>
                                            <select id="consultation_type_{{ $appt->id }}" name="consultation_type_id" required class="rounded-lg border-slate-300 py-1.5 text-xs shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                                <option value="">Select charge…</option>
                                                @foreach ($departmentTypes as $consultationType)
                                                    <option value="{{ $consultationType->id }}">{{ $consultationType->name }} · ₦{{ number_format((float) $consultationType->price, 2) }}</option>
                                                @endforeach
                                            </select>
                                            <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Check in</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-amber-700" title="Add an active consultation type in admin pricing">No consultation price set</span>
                                    @endif
                                    <form method="POST" action="{{ route('appointments.cancel', $appt) }}"
                                          onsubmit="return confirm('Cancel this appointment?')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="text-xs text-red-600 hover:underline">Cancel</button>
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
