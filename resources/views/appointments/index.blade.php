<x-app-layout>
    <x-slot name="header">Appointments</x-slot>

    @php $frontDesk = auth()->user()->hasRole('admin', 'receptionist'); @endphp

    @php
        $appointmentFilters = array_filter([
            'department_id' => $departmentId,
            'doctor_id' => $doctorId,
            'hospital_number' => $hospitalNumber,
        ], fn ($value) => filled($value));
    @endphp

    <div class="mb-6 flex flex-col gap-4 rounded-xl border bg-white p-4 shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" action="{{ route('appointments.index') }}" class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="appointment_date_filter" class="block text-xs font-medium text-slate-600">Appointment date</label>
                <input id="appointment_date_filter" type="date" name="date" value="{{ $date->toDateString() }}"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
            </div>

            @if ($frontDesk)
                <div>
                    <label for="appointment_department_filter" class="block text-xs font-medium text-slate-600">Department</label>
                    <select id="appointment_department_filter" name="department_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        <option value="">All departments</option>
                        @foreach ($appointmentDepartments as $department)
                            <option value="{{ $department->id }}" @selected((string) $departmentId === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="appointment_doctor_filter" class="block text-xs font-medium text-slate-600">Doctor</label>
                    <select id="appointment_doctor_filter" name="doctor_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                        <option value="">All doctors</option>
                        @foreach ($appointmentDoctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((string) $doctorId === (string) $doctor->id)>{{ $doctor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="appointment_patient_filter" class="block text-xs font-medium text-slate-600">Patient hospital number</label>
                    <input id="appointment_patient_filter" type="search" name="hospital_number" value="{{ $hospitalNumber }}"
                           placeholder="e.g. HN-26-00001"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                </div>
            @endif

            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Filter</button>
                @if ($appointmentFilters !== [])
                    <a href="{{ route('appointments.index', ['date' => $date->toDateString()]) }}" class="px-2 py-2 text-sm text-slate-600 hover:underline">Clear filters</a>
                @endif
            </div>
        </form>

        <div class="flex items-center gap-2">
            <a href="{{ route('appointments.index', ['date' => $date->copy()->subDay()->toDateString()] + $appointmentFilters) }}"
               aria-label="Previous day" class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">‹</a>
            @unless ($date->isToday())
                <a href="{{ route('appointments.index', ['date' => today()->toDateString()] + $appointmentFilters) }}" class="text-sm text-teal-700 hover:underline">Today</a>
            @endunless
            <a href="{{ route('appointments.index', ['date' => $date->copy()->addDay()->toDateString()] + $appointmentFilters) }}"
               aria-label="Next day" class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">›</a>

            @if ($frontDesk)
                <a href="{{ route('appointments.create') }}"
                   class="ml-2 whitespace-nowrap rounded-lg bg-teal-700 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-teal-800">
                    + Book appointment
                </a>
            @endif
        </div>
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
                                @foreach ($appt->reassignments as $reassignment)
                                    <span class="mt-1 block max-w-xs text-xs text-slate-500" title="{{ $reassignment->reason }}">
                                        Reassigned from {{ $reassignment->fromDoctor?->name ?? 'a previous doctor' }} by {{ $reassignment->reassignedBy?->name ?? 'staff' }} · {{ $reassignment->reason }}
                                    </span>
                                @endforeach
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
                                <div class="flex flex-col items-end gap-2">
                                    <div class="flex items-center justify-end gap-3">
                                    @php($departmentTypes = $consultationTypesByDepartment->get($appt->doctor?->department_id, collect()))
                                    @if (! $appt->scheduled_at->isToday())
                                        <span class="self-center text-xs text-slate-500">Check-in on {{ $appt->scheduled_at->format('d M Y') }}</span>
                                    @elseif ($appt->isLab())
                                        <form method="POST" action="{{ route('appointments.check-in', $appt) }}">
                                            @csrf
                                            <button onclick="return confirm('Confirm this lab appointment is scheduled for today before checking in?')" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Check in</button>
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
                                            <button onclick="return confirm('Confirm this appointment is scheduled for today before checking in?')" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Check in</button>
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
                                    @php($replacementDoctors = $reassignmentDoctors->get($appt->id, collect()))
                                    @if (! $appt->isLab() && $appt->scheduled_at->toDateString() >= today()->toDateString())
                                        <details class="text-left">
                                            <summary class="cursor-pointer text-xs font-medium text-teal-700 hover:underline">Reassign doctor</summary>
                                            @if ($replacementDoctors->isNotEmpty())
                                                <form method="POST" action="{{ route('appointments.reassign-doctor', $appt) }}" class="mt-2 flex flex-col gap-2 rounded-lg border bg-slate-50 p-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="sr-only" for="replacement_doctor_{{ $appt->id }}">Replacement doctor</label>
                                                    <select id="replacement_doctor_{{ $appt->id }}" name="doctor_id" required class="rounded-lg border-slate-300 py-1.5 text-xs shadow-sm">
                                                        <option value="">Choose available doctor…</option>
                                                        @foreach ($replacementDoctors as $replacementDoctor)
                                                            <option value="{{ $replacementDoctor->id }}">{{ $replacementDoctor->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <label class="sr-only" for="reassignment_reason_{{ $appt->id }}">Reason for reassignment</label>
                                                    <input id="reassignment_reason_{{ $appt->id }}" name="reason" required maxlength="255" placeholder="Reason for change" class="rounded-lg border-slate-300 py-1.5 text-xs shadow-sm">
                                                    <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Save reassignment</button>
                                                </form>
                                            @else
                                                <p class="mt-2 max-w-xs rounded-lg border bg-slate-50 p-2 text-xs text-slate-600">
                                                    No other active doctor in this department is marked available on this date. Add that doctor's availability for {{ $appt->scheduled_at->format('d M Y') }} first.
                                                </p>
                                            @endif
                                        </details>
                                    @endif
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
