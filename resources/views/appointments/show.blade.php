<x-app-layout>
    <x-slot name="header">Appointment</x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-sm text-teal-700">{{ $appointment->patient->hospital_number }}</p>
                    <h2 class="text-xl font-semibold">{{ $appointment->patient->full_name }}</h2>
                    <p class="text-sm text-slate-500">{{ ucfirst($appointment->patient->gender) }} · {{ $appointment->patient->age }} yrs</p>
                </div>
                <x-badge :color="$appointment->status->color()">{{ $appointment->status->label() }}</x-badge>
            </div>

            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">{{ $appointment->isLab() ? 'Date and time' : 'Appointment date' }}</dt>
                    <dd class="font-medium">
                        {{ $appointment->isLab() ? $appointment->scheduled_at->format('d M Y, g:i A') : $appointment->scheduled_at->format('d M Y') }}
                        @unless ($appointment->isLab())<span class="block text-xs font-normal text-slate-500">Patients are seen in the order they arrive.</span>@endunless
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">{{ $appointment->isLab() ? 'Appointment type' : 'Doctor' }}</dt>
                    <dd class="font-medium">
                        @if ($appointment->isLab())
                            Lab test
                        @else
                            {{ $appointment->doctor?->name ?? 'Not assigned' }}
                        @endif
                    </dd>
                </div>
                @if ($appointment->isLab())
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Tests</dt>
                        <dd class="font-medium">{{ $appointment->labTests->pluck('name')->join(', ') ?: 'No tests listed' }}</dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt class="text-slate-500">Reason</dt>
                    <dd class="whitespace-pre-line font-medium">{{ $appointment->reason ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <a href="{{ route('patients.show', $appointment->patient) }}" class="text-sm text-teal-700 hover:underline">Back to patient profile</a>
    </div>
</x-app-layout>
<div>
    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
</div>
