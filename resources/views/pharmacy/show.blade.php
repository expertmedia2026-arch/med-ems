<x-app-layout>
    <x-slot name="header">Dispense: {{ $visit->patient->full_name }}</x-slot>

    @php
        $patient = $visit->patient;
        $pending = $visit->prescriptions->filter(fn ($rx) => $rx->status->value === 'pending');
        $done    = $visit->prescriptions->filter(fn ($rx) => $rx->status->value === 'completed');
    @endphp

    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="text-sm text-slate-500">
            {{ ucfirst($patient->gender) }} · {{ $patient->age }} yrs · Prescribed by {{ $visit->doctor?->name ?? '—' }}
        </p>

        @if ($patient->allergies)
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                <strong>Allergies:</strong> {{ $patient->allergies }}
            </p>
        @endif
    </div>

    @if ($pending->isNotEmpty())
       @php
            $dispensable = $pending->filter(fn ($rx) => $rx->is_paid
                && $rx->stockReservation?->status === 'reserved'
                && $rx->stockReservation->quantity >= ($rx->dispensable_quantity ?? $rx->quantity));
    @endphp

        <form method="POST" action="{{ route('pharmacy.dispense', $visit) }}" class="mb-8 rounded-xl border bg-white shadow-sm">
            @csrf
            <h3 class="border-b px-5 py-3 font-semibold">To dispense</h3>

            <ul class="divide-y text-sm">
                @foreach ($pending as $rx)
                    @php
                        $reservedQuantity = $rx->stockReservation?->status === 'reserved' ? $rx->stockReservation->quantity : 0;
                        $dispenseQuantity = $rx->dispensable_quantity ?? $rx->quantity;
                        $inStock = $reservedQuantity >= $dispenseQuantity;
                        $ok      = $inStock && $rx->is_paid;
                    @endphp
                    <li class="flex items-start gap-3 px-5 py-4">
                        <input type="checkbox" name="prescription_ids[]" value="{{ $rx->id }}"
                            @checked($ok) @disabled(! $ok)
                            class="mt-1 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                        <div class="flex-1">
                            <p class="font-medium">{{ $rx->drug->display_name }} · {{ $dispenseQuantity }} reserved of {{ $rx->quantity }} prescribed</p>
                            @if ($rx->quantity > $dispenseQuantity)
                                <p class="text-amber-700">{{ $rx->quantity - $dispenseQuantity }} unit(s) unavailable; arrange an alternative or external supply.</p>
                            @endif
                            <p class="text-slate-600">{{ $rx->dosage }}, {{ $rx->frequency }}, for {{ $rx->duration }}</p>
                            @if ($rx->instructions)
                                <p class="text-slate-500">{{ $rx->instructions }}</p>
                            @endif
                        </div>
                        <div class="space-y-1 text-right">
                            @if ($rx->is_paid)
                                <x-badge color="green">Paid</x-badge>
                            @else
                                <x-badge color="amber">Awaiting payment</x-badge>
                            @endif

                            @if ($inStock)
                                <span class="block text-xs text-slate-500">{{ $rx->drug->stock_quantity }} on hand · {{ $rx->drug->reserved_quantity }} reserved</span>
                            @else
                                <x-badge color="red">Reservation expired or unavailable ({{ $rx->drug->available_quantity }} available)</x-badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @error('prescription_ids')<p class="px-5 pb-2 text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex items-center gap-3 border-t px-5 py-4">
                <button @disabled($dispensable->isEmpty())
                        class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Dispense selected
                </button>
                <a href="{{ route('pharmacy.index') }}" class="text-sm text-slate-500 hover:underline">Back to queue</a>
                @if ($dispensable->isEmpty())
                    <span class="text-xs text-amber-700">Nothing can be dispensed yet: items are unpaid or out of stock.</span>
                @endif
            </div>
        </form>
    @else
        <p class="mb-8 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            Everything on this prescription has been dispensed.
            <a href="{{ route('pharmacy.index') }}" class="font-medium underline">Back to queue</a>
        </p>
    @endif

    @if ($done->isNotEmpty())
        <div class="rounded-xl border bg-white shadow-sm">
            <h3 class="border-b px-5 py-3 font-semibold">Already dispensed</h3>
            <ul class="divide-y text-sm">
                @foreach ($done as $rx)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span>{{ $rx->drug->display_name }} × {{ $rx->dispensable_quantity ?? $rx->quantity }}</span>
                        <span class="text-xs text-slate-500">{{ $rx->dispensed_at->format('d M, g:i A') }} · {{ $rx->dispenser?->name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-app-layout>
