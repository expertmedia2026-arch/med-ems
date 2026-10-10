<x-portal-layout>
    <x-slot name="header">Invoice</x-slot>

    @php
        $balance  = round($invoice->balance, 2);
        $catColor = ['registration' => 'blue', 'consultation' => 'blue', 'lab' => 'amber', 'drug' => 'green'];
    @endphp

    <a href="{{ route('portal.invoices.index') }}" class="mb-4 inline-block text-sm text-teal-700 hover:underline">← Back to invoices</a>

    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-sm text-teal-700">{{ $invoice->invoice_number }}</p>
                <h2 class="text-xl font-semibold">
                    @if ($invoice->visit)
                        {{ $invoice->visit->isLabOnly() ? 'Laboratory visit' : 'Consultation' }} · {{ $invoice->visit->checked_in_at->format('d M Y') }}
                    @else
                        Registration
                    @endif
                </h2>
            </div>
            <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
        </div>
    </div>

    <form method="POST" action="{{ route('portal.invoices.pay', $invoice) }}"
          x-data="{
              items: @js($payable->map(fn ($i) => ['id' => (string) $i->id, 'total' => (float) $i->total])->values()),
              selected: @js($payable->pluck('id')->map(fn ($i) => (string) $i)->values()),
              get total() { return this.items.filter(i => this.selected.includes(i.id)).reduce((s, i) => s + i.total, 0) },
              get allSelected() { return this.items.length > 0 && this.selected.length === this.items.length },
              toggleAll() { this.selected = this.allSelected ? [] : this.items.map(i => i.id) },
              money(n) { return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
          }">
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">

                {{-- Items --}}
                <div class="rounded-xl border bg-white shadow-sm">
                    <h3 class="border-b px-5 py-3 font-semibold">Charges</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-10 px-4 py-2">
                                        @if ($payable->isNotEmpty())
                                            <input type="checkbox" :checked="allSelected" @change="toggleAll()"
                                                   class="rounded border-slate-300 text-teal-700 focus:ring-teal-600" title="Select all">
                                        @endif
                                    </th>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-4 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            @if ($payable->contains('id', $item->id))
                                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" x-model="selected"
                                                       class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ $item->description }}
                                            <x-badge :color="$catColor[$item->category] ?? 'slate'" class="ml-2">{{ ucfirst($item->category) }}</x-badge>
                                        </td>
                                        <td class="px-3 py-3 text-right">{{ $item->quantity }}</td>
                                        <td class="px-3 py-3 text-right font-medium">₦{{ number_format($item->total, 2) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->payment)
                                                <a href="{{ route('portal.payments.receipt', $item->payment) }}" target="_blank"
                                                   class="text-xs text-green-700 hover:underline">Paid · {{ $item->payment->receipt_number }}</a>
                                            @elseif ($item->is_paid)
                                                <span class="text-xs text-slate-500">No charge</span>
                                            @elseif ($waiting->contains('id', $item->id))
                                                <x-badge color="amber">After consultation</x-badge>
                                            @else
                                                <x-badge color="amber">Unpaid</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 text-sm">
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-slate-500">Total</td>
                                    <td class="px-3 py-2 text-right font-semibold">₦{{ number_format($invoice->total, 2) }}</td><td></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-slate-500">Paid</td>
                                    <td class="px-3 py-2 text-right text-green-700">₦{{ number_format($invoice->amount_paid, 2) }}</td><td></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right font-semibold">Balance</td>
                                    <td class="px-3 py-2 text-right text-lg font-bold {{ $balance > 0 ? 'text-red-700' : 'text-green-700' }}">₦{{ number_format($balance, 2) }}</td><td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Payments --}}
                <div class="rounded-xl border bg-white shadow-sm">
                    <h3 class="border-b px-5 py-3 font-semibold">Payments</h3>
                    <ul class="divide-y text-sm">
                        @forelse ($invoice->payments as $payment)
                            <li class="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p class="font-medium">
                                        ₦{{ number_format($payment->amount, 2) }}
                                        <span class="ml-1 text-slate-500">{{ $payment->method === 'online' ? 'paid online' : 'via ' . strtoupper($payment->method) }}</span>
                                    </p>
                                    <p class="font-mono text-xs text-slate-400">{{ $payment->receipt_number }}</p>
                                </div>
                                <div class="flex items-center gap-5">
                                    <span class="text-xs text-slate-500">{{ $payment->created_at->format('d M Y, g:i A') }}</span>
                                    <a href="{{ route('portal.payments.receipt', $payment) }}" target="_blank" class="text-teal-700 hover:underline">Receipt</a>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-500">No payments yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Pay --}}
            <div>
                @if ($payable->isNotEmpty())
                    <div class="rounded-xl border bg-white p-5 shadow-sm">
                        <h3 class="font-semibold">Pay online</h3>
                        <p class="mt-1 text-xs text-slate-500">Tick what you want to pay for now.</p>

                        <div class="mt-4 rounded-lg bg-teal-50 px-4 py-3">
                            <p class="text-xs text-teal-700">Amount to pay</p>
                            <p class="text-2xl font-bold text-teal-900" x-text="money(total)"></p>
                        </div>

                        @error('item_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                        <button :disabled="selected.length === 0"
                                class="mt-5 w-full rounded-lg bg-teal-700 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Pay securely online
                        </button>

                        <p class="mt-3 text-xs text-slate-400">
                            You will be taken to Paystack to pay by card, bank transfer or USSD, then returned here with your receipt.
                        </p>
                    </div>
                @elseif ($balance > 0)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                        <p class="font-semibold">Not ready to pay yet</p>
                        <p class="mt-1">Prescribed drugs can be paid once your consultation is completed.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-green-200 bg-green-50 p-5 text-sm text-green-800">
                        <p class="font-semibold">Nothing to pay</p>
                        <p class="mt-1">This invoice is fully paid.</p>
                    </div>
                @endif
            </div>
        </div>
    </form>
</x-portal-layout>