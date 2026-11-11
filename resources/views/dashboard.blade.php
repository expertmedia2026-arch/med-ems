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

    @if ($standaloneCharts)
        @php
            $maxRevenue = max(1, collect($standaloneCharts['revenueTrend'])->max('amount') ?? 0);
            $maxPayment = max(1, collect($standaloneCharts['paymentMethods'])->max('amount') ?? 0);
            $maxExpiry = max(1, collect($standaloneCharts['expiryRisk'])->max('units') ?? 0);
        @endphp
        <div class="mt-8 grid gap-6 xl:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h3 class="font-semibold text-slate-900">Revenue over the last 7 days</h3><p class="mt-1 text-xs text-slate-500">Daily completed retail sales</p></div>
                    <a href="{{ route('standalone-pharmacy.sales.index') }}" class="text-xs font-semibold text-teal-700 hover:underline">Open sales report →</a>
                </div>
                <div class="mt-6 flex h-56 items-end gap-2 border-b border-slate-200 px-1 sm:gap-4">
                    @foreach ($standaloneCharts['revenueTrend'] as $day)
                        @php $height = $day['amount'] > 0 ? max(5, ($day['amount'] / $maxRevenue) * 100) : 0; @endphp
                        <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2" title="{{ $day['sales'] }} sales · ₦{{ number_format($day['amount'], 2) }}">
                            <span class="max-w-full truncate text-[10px] font-medium text-slate-500">{{ $day['amount'] > 0 ? '₦'.number_format($day['amount'], 0) : '' }}</span>
                            <div class="w-full max-w-14 rounded-t-lg bg-teal-700 transition hover:bg-teal-800" style="height: {{ $height }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2 sm:gap-4">@foreach ($standaloneCharts['revenueTrend'] as $day)<span class="min-w-0 flex-1 text-center text-xs font-medium text-slate-500">{{ $day['label'] }}</span>@endforeach</div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="font-semibold text-slate-900">Payment methods</h3><p class="mt-1 text-xs text-slate-500">Last 7 days by collected value</p>
                <div class="mt-5 space-y-5">
                    @forelse ($standaloneCharts['paymentMethods'] as $method)
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm"><span class="font-medium text-slate-700">{{ $method['method'] }} <span class="text-xs font-normal text-slate-400">· {{ $method['sales'] }} sales</span></span><span class="font-semibold text-slate-900">₦{{ number_format($method['amount'], 2) }}</span></div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width: {{ ($method['amount'] / $maxPayment) * 100 }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-12 text-center text-sm text-slate-500">No completed sales in the last 7 days.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3"><div><h3 class="font-semibold text-slate-900">Expiry risk</h3><p class="mt-1 text-xs text-slate-500">Units currently held in expired or near-expiry batches</p></div><a href="{{ route('standalone-pharmacy.sales.index') }}" class="text-xs font-semibold text-teal-700 hover:underline">Review expiry alerts →</a></div>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    @foreach ($standaloneCharts['expiryRisk'] as $risk)
                        @php
                            $riskTone = match ($risk['label']) {
                                'Expired' => 'bg-red-600',
                                'Within 30 days' => 'bg-orange-500',
                                default => 'bg-amber-400',
                            };
                        @endphp
                        <div class="rounded-lg bg-slate-50 p-4"><div class="flex items-center justify-between gap-3"><span class="text-sm font-medium text-slate-600">{{ $risk['label'] }}</span><span class="text-lg font-bold text-slate-900">{{ number_format($risk['units']) }}</span></div><div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full {{ $riskTone }}" style="width: {{ ($risk['units'] / $maxExpiry) * 100 }}%"></div></div></div>
                    @endforeach
                </div>
            </section>
        </div>
    @endif

    @if (! auth()->user()->hospital?->isStandalonePharmacy() && ($queue->isNotEmpty() || auth()->user()->hasRole('admin', 'receptionist', 'nurse', 'doctor')))
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
