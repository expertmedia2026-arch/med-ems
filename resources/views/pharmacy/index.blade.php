<x-app-layout>
    <x-slot name="header">Pharmacy</x-slot>

    @include('pharmacy._tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="mb-3 font-semibold">Waiting to be dispensed ({{ $queue->count() }})</h2>
            <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Patient</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Waiting</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($queue as $visit)
                            @php
                                $total   = $visit->prescriptions->count();
                                $paid    = $visit->prescriptions->filter(fn ($rx) => $rx->is_paid)->count();
                                $allPaid = $paid === $total;
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $visit->patient->full_name }}</span>
                                    <span class="block font-mono text-xs text-slate-400">{{ $visit->patient->hospital_number }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $total }} drug(s)
                                    <span class="block text-xs text-slate-400">{{ $visit->prescriptions->pluck('drug.name')->join(', ') }}</span>
                                    <span class="block text-xs {{ $allPaid ? 'text-green-700' : 'text-amber-700' }}">
                                        {{ $paid }} of {{ $total }} paid
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $visit->closed_at->diffForHumans(short: true) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('pharmacy.show', $visit) }}"
                                       class="rounded-lg px-3 py-1.5 text-xs font-medium {{ $paid > 0 ? 'bg-teal-700 text-white hover:bg-teal-800' : 'border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                                        {{ $paid > 0 ? 'Dispense' : 'View (unpaid)' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Nothing waiting.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="mb-3 mt-8 font-semibold">Dispensed today</h2>
            <div class="rounded-xl border bg-white shadow-sm">
                <ul class="divide-y text-sm">
                    @forelse ($dispensedToday as $rx)
                        <li class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="font-medium">{{ $rx->drug->display_name }} × {{ $rx->quantity }}</p>
                                <p class="text-slate-500">{{ $rx->visit->patient->full_name }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                {{ $rx->dispensed_at->format('g:i A') }}
                                <span class="block">{{ $rx->dispenser?->name }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">Nothing dispensed yet today.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div>
            <div class="rounded-xl border border-amber-200 bg-white shadow-sm">
                <h3 class="border-b border-amber-200 bg-amber-50 px-5 py-3 font-semibold text-amber-900">Low stock</h3>
                <ul class="divide-y text-sm">
                    @forelse ($lowStock as $drug)
                        <li class="flex items-center justify-between px-5 py-3">
                            <span>{{ $drug->display_name }}</span>
                            <x-badge color="red">{{ $drug->available_quantity }} available</x-badge>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">All stocked up.</li>
                    @endforelse
                </ul>
                @if ($lowStock->isNotEmpty())
                    <a href="{{ route('pharmacy.inventory', ['low' => 1]) }}" class="block border-t px-5 py-3 text-sm text-teal-700 hover:underline">Restock →</a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
