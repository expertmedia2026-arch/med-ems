<x-portal-layout>
    <x-slot name="header">Appointments</x-slot>

    <div class="mb-6 flex flex-wrap gap-3">
        <a href="{{ route('portal.appointments.create', ['type' => 'consultation']) }}"
           class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-teal-800">+ See a doctor</a>
        <a href="{{ route('portal.appointments.create', ['type' => 'lab']) }}"
           class="rounded-lg border border-teal-700 px-4 py-2.5 text-sm font-medium text-teal-700 hover:bg-teal-50">+ Lab test</a>
    </div>

    <h2 class="mb-3 font-semibold">Upcoming ({{ $upcoming->count() }})</h2>
    <div class="mb-10 space-y-3">
        @forelse ($upcoming as $appt)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-5 shadow-sm">
                <div>
                    <p class="font-semibold">{{ $appt->scheduled_at->format('l, d M Y') }}
                        <span class="font-normal text-teal-700">· {{ $appt->scheduled_at->format('g:i A') }}</span>
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($appt->isLab())
                            Lab test: {{ $appt->labTests->pluck('name')->join(', ') ?: '—' }}
                        @else
                            {{ $appt->doctor?->name }}{{ $appt->reason ? ' · ' . $appt->reason : '' }}
                        @endif
                    </p>
                </div>

                <form method="POST" action="{{ route('portal.appointments.cancel', $appt) }}"
                      onsubmit="return confirm('Cancel this appointment?')">
                    @csrf @method('PATCH')
                    <button class="text-sm text-red-600 hover:underline">Cancel</button>
                </form>
            </div>
        @empty
            <div class="rounded-xl border bg-white p-8 text-center text-sm text-slate-500">You have no upcoming appointments.</div>
        @endforelse

        @if ($upcoming->isNotEmpty())
            <p class="text-xs text-slate-400">Please arrive a few minutes early and check in at the front desk. Fees are paid at the cashier.</p>
        @endif
    </div>

    <h2 class="mb-3 font-semibold">Past appointments</h2>
    <div class="rounded-xl border bg-white shadow-sm">
        <ul class="divide-y text-sm">
            @forelse ($past as $appt)
                <li class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="font-medium">{{ $appt->scheduled_at->format('d M Y, g:i A') }}</p>
                        <p class="text-slate-500">
                            {{ $appt->isLab() ? 'Lab test: ' . $appt->labTests->pluck('name')->join(', ') : ($appt->doctor?->name ?? '') }}
                        </p>
                    </div>
                    {{-- A booking that was never attended shows as Missed rather than still "Scheduled" --}}
                    @if ($appt->status->value === 'scheduled')
                        <x-badge color="red">Missed</x-badge>
                    @else
                        <x-badge :color="$appt->status->color()">{{ $appt->status->label() }}</x-badge>
                    @endif
                </li>
            @empty
                <li class="px-5 py-6 text-center text-slate-500">No past appointments.</li>
            @endforelse
        </ul>
    </div>
</x-portal-layout>