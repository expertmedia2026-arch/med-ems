<x-portal-layout>
    <x-slot name="header">Visit Details</x-slot>

    @php
        $vitals   = $visit->vitals;
        $invoice  = $visit->invoice;
        $catColor = ['consultation' => 'blue', 'lab' => 'amber', 'drug' => 'green'];
        $hasDoneLabs = $visit->labRequests->contains(fn ($request) => $request->status->value === 'completed');
        $labResultsVisible = $visit->patient_lab_results_visible ?? false;
    @endphp

    <a href="{{ route('portal.visits.index') }}" class="mb-4 inline-block text-sm text-teal-700 hover:underline">← Back to my visits</a>

    {{-- Summary --}}
    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">{{ $visit->checked_in_at->format('l, d F Y') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $visit->isLabOnly() ? 'Laboratory visit' : 'Consultation' }}
                    @if ($visit->doctor) · {{ $visit->doctor->name }} @endif
                    · Checked in {{ $visit->checked_in_at->format('g:i A') }}
                </p>
                @if ($visit->isLabOnly() && $visit->referred_from)
                    <p class="mt-1 text-sm text-slate-500">Referred from {{ $visit->referred_from }}</p>
                @endif
            </div>
            <x-badge :color="$visit->status->color()">{{ $visit->status->label() }}</x-badge>
        </div>
    </div>

    <div class="max-w-4xl space-y-6">

        @if ($inProgress)
            @if ($visit->status->value === 'inpatient')
                <div class="rounded-xl border border-teal-200 bg-teal-50 px-5 py-4 text-sm text-teal-900">
                    <p class="font-semibold">You are currently admitted as an inpatient</p>
                    <p class="mt-1">Your care team is managing your hospital stay. Visit notes and results will appear here after discharge when they are ready to share.</p>
                </div>
            @else
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    <p class="font-semibold">This visit is still in progress</p>
                    <p class="mt-1">Visit details, test results, and prescriptions will appear here once staff complete the visit.</p>
                </div>
            @endif
        @else

            {{-- Vitals --}}
            @if (! $visit->isLabOnly())
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <h3 class="mb-4 font-semibold">Vitals</h3>

                    @if ($vitals)
                        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4 lg:grid-cols-7">
                            @foreach ([
                                'Temp'   => $vitals->temperature ? $vitals->temperature . '°C' : null,
                                'Pulse'  => $vitals->pulse ? $vitals->pulse . ' bpm' : null,
                                'BP'     => $vitals->blood_pressure,
                                'Resp'   => $vitals->respiratory_rate,
                                'SpO₂'   => $vitals->spo2 ? $vitals->spo2 . '%' : null,
                                'Weight' => $vitals->weight ? $vitals->weight . ' kg' : null,
                                'BMI'    => $vitals->bmi,
                            ] as $label => $value)
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <p class="text-xs text-slate-500">{{ $label }}</p>
                                    <p class="font-semibold">{{ $value ?: '—' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No vitals were recorded for this visit.</p>
                    @endif
                </div>
            @endif

            {{-- Only the note specifically written for the patient is shown here. --}}
            @if ($visit->patient_visible_note)
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <h3 class="mb-3 font-semibold">Note from your doctor</h3>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $visit->patient_visible_note }}</p>
                </div>
            @endif

            @if ($visit->admission?->patient_discharge_instructions)
                <div class="rounded-xl border border-teal-200 bg-white p-6 shadow-sm">
                    <h3 class="mb-3 font-semibold">Discharge instructions</h3>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $visit->admission->patient_discharge_instructions }}</p>
                </div>
            @endif

            {{-- Lab --}}
            <div class="rounded-xl border bg-white p-6 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold">{{ $labResultsVisible ? 'Lab tests and results' : 'Lab tests' }}</h3>
                    @if ($labResultsVisible && $hasDoneLabs)
                        <a href="{{ route('portal.visits.lab-report', $visit) }}" target="_blank"
                           class="rounded-lg border border-teal-700 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">
                            View or print results
                        </a>
                    @endif
                </div>

                @forelse ($visit->labRequests as $req)
                    <div class="border-t py-3 first:border-t-0 first:pt-0">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ $req->test->name }}</span>
                            <x-badge :color="$req->status->value === 'pending' ? 'amber' : 'green'">
                                {{ $req->status->value === 'pending' ? 'Awaiting result' : 'Result ready' }}
                            </x-badge>
                        </div>
                        @if ($req->status->value === 'completed')
                            @if ($labResultsVisible)
                                @if ($req->result)
                                    <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm">{{ $req->result }}</p>
                                @endif
                                @include('lab._attachments', ['attachments' => $req->attachments, 'canManage' => false])
                                <p class="mt-2 text-xs text-slate-400">Completed {{ $req->completed_at->format('d M Y, g:i A') }}</p>
                            @elseif ($visit->email_lab_results)
                                <p class="mt-2 text-xs text-slate-500">Your result has been sent to your email address.</p>
                            @else
                                <p class="mt-2 text-xs text-slate-500">Your result has been uploaded and shared with your care team. Please contact your doctor or the hospital to discuss it.</p>
                            @endif
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No lab tests were requested.</p>
                @endforelse
            </div>

            {{-- Prescriptions --}}
            @unless ($visit->isLabOnly())
                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <h3 class="mb-4 font-semibold">Prescribed drugs</h3>

                    @if ($visit->prescriptions->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y text-sm">
                                <thead class="text-left text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">Drug</th>
                                        <th class="pr-4">Dosage</th>
                                        <th class="pr-4">Frequency</th>
                                        <th class="pr-4">Duration</th>
                                        <th class="pr-4">Qty</th>
                                        <th>Pharmacy</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($visit->prescriptions as $rx)
                                        <tr>
                                            <td class="py-2 pr-4 font-medium">
                                                {{ $rx->drug->display_name }}
                                                @if ($rx->instructions)
                                                    <span class="block text-xs font-normal text-slate-400">{{ $rx->instructions }}</span>
                                                @endif
                                            </td>
                                            <td class="pr-4">{{ $rx->dosage }}</td>
                                            <td class="pr-4">{{ $rx->frequency }}</td>
                                            <td class="pr-4">{{ $rx->duration }}</td>
                                            <td class="pr-4">{{ $rx->quantity }}</td>
                                            <td>
                                                @if ($rx->status->value === 'completed')
                                                    <x-badge color="green">Dispensed</x-badge>
                                                @else
                                                    <x-badge color="amber">Not yet dispensed</x-badge>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">No drugs were prescribed.</p>
                    @endif
                </div>
            @endunless
        @endif

        {{-- Billing (always visible so the patient knows what is owed) --}}
        @if ($invoice)
            <div class="rounded-xl border bg-white shadow-sm">
                <div class="flex items-center justify-between border-b px-6 py-3">
                    <h3 class="font-semibold">Bill for this visit</h3>
                    <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
                    @if ($invoice->balance > 0)
                        <a href="{{ route('portal.invoices.show', $invoice) }}" class="mr-3 text-sm font-medium text-teal-700 hover:underline">Pay online</a>
                    @endif
                </div>

                <ul class="divide-y text-sm">
                    @foreach ($invoice->items as $item)
                        <li class="flex items-center justify-between px-6 py-3">
                            <div>
                                <span class="font-medium">{{ $item->description }}</span>
                                @if ($item->quantity > 1)<span class="text-slate-400"> × {{ $item->quantity }}</span>@endif
                                <x-badge :color="$catColor[$item->category] ?? 'slate'" class="ml-2">{{ ucfirst($item->category) }}</x-badge>
                            </div>
                            <div class="flex items-center gap-4">
                                <span>₦{{ number_format($item->total, 2) }}</span>
                                <x-badge :color="$item->is_paid ? 'green' : 'amber'">{{ $item->is_paid ? 'Paid' : 'Unpaid' }}</x-badge>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="space-y-1 border-t px-6 py-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Total</span><span class="font-semibold">₦{{ number_format($invoice->total, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Paid</span><span class="text-green-700">₦{{ number_format($invoice->amount_paid, 2) }}</span></div>
                    <div class="flex justify-between"><span class="font-semibold">Balance</span>
                        <span class="font-bold {{ $invoice->balance > 0 ? 'text-red-700' : 'text-green-700' }}">₦{{ number_format($invoice->balance, 2) }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-portal-layout>
