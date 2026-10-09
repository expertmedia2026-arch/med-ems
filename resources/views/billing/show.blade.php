<x-app-layout>
    <x-slot name="header">Invoice {{ $invoice->invoice_number }}</x-slot>

    @php
        $patient  = $invoice->patient;
        $input    = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $unpaid   = $invoice->items->filter(fn ($i) => ! $i->is_paid);
        $balance  = round($invoice->balance, 2);
        $catColor = ['consultation' => 'blue', 'lab' => 'amber', 'drug' => 'green'];
    @endphp

    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
                <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
                <p class="text-sm text-slate-500">
                    {{ $invoice->visit->doctor ? 'Doctor: ' . $invoice->visit->doctor->name . ' · ' : '' }}Visit {{ $invoice->visit->checked_in_at->format('d M Y') }}
                </p>
            </div>
            <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
        </div>
    </div>

    <form method="POST" action="{{ route('billing.pay', $invoice) }}"
          x-data="{
              items: @js($unpaid->map(fn ($i) => ['id' => (string) $i->id, 'total' => (float) $i->total])->values()),
              selected: @js($unpaid->pluck('id')->map(fn ($i) => (string) $i)->values()),
              method: 'cash',
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
                                        @if ($unpaid->isNotEmpty())
                                            <input type="checkbox" :checked="allSelected" @change="toggleAll()"
                                                   class="rounded border-slate-300 text-teal-700 focus:ring-teal-600" title="Select all">
                                        @endif
                                    </th>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-right">Unit price</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-4 py-2">Payment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            @unless ($item->is_paid)
                                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" x-model="selected"
                                                       class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                            @endunless
                                        </td>
                                        <td class="px-3 py-3">
                                            {{ $item->description }}
                                            <x-badge :color="$catColor[$item->category] ?? 'slate'" class="ml-2">{{ ucfirst($item->category) }}</x-badge>
                                        </td>
                                        <td class="px-3 py-3 text-right">{{ $item->quantity }}</td>
                                        <td class="px-3 py-3 text-right">₦{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="px-3 py-3 text-right font-medium">₦{{ number_format($item->total, 2) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->payment)
                                                <a href="{{ route('billing.receipt', $item->payment) }}" target="_blank"
                                                   class="text-xs text-green-700 hover:underline">Paid · {{ $item->payment->receipt_number }}</a>
                                            @elseif ($item->is_paid)
                                                <span class="text-xs text-slate-500">No charge</span>
                                            @else
                                                <x-badge color="amber">Unpaid</x-badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 text-sm">
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-slate-500">Total</td>
                                    <td class="px-3 py-2 text-right font-semibold">₦{{ number_format($invoice->total, 2) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right text-slate-500">Paid</td>
                                    <td class="px-3 py-2 text-right text-green-700">₦{{ number_format($invoice->amount_paid, 2) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right font-semibold">Balance</td>
                                    <td class="px-3 py-2 text-right text-lg font-bold {{ $balance > 0 ? 'text-red-700' : 'text-green-700' }}">₦{{ number_format($balance, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Payment history --}}
                <div class="rounded-xl border bg-white shadow-sm">
                    <h3 class="border-b px-5 py-3 font-semibold">Payments and receipts</h3>
                    <ul class="divide-y text-sm">
                        @forelse ($invoice->payments as $payment)
                            <li class="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p class="font-medium">
                                        ₦{{ number_format($payment->amount, 2) }}
                                        <span class="ml-1 text-slate-500">via {{ strtoupper($payment->method) }}</span>
                                    </p>
                                    <p class="font-mono text-xs text-slate-400">{{ $payment->receipt_number }}{{ $payment->reference ? ' · Ref: ' . $payment->reference : '' }}</p>
                                </div>
                                <div class="flex items-center gap-5">
                                    <div class="text-right text-xs text-slate-500">
                                        {{ $payment->created_at->format('d M, g:i A') }}
                                        <span class="block">{{ $payment->receiver?->name }}</span>
                                    </div>
                                    <a href="{{ route('billing.receipt', $payment) }}" target="_blank" class="text-teal-700 hover:underline">Receipt</a>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-slate-500">No payments yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Collect payment --}}
            <div>
                @if ($unpaid->isNotEmpty())
                    <div class="rounded-xl border bg-white p-5 shadow-sm">
                        <h3 class="font-semibold">Collect payment</h3>
                        <p class="mt-1 text-xs text-slate-500">Tick the items the patient is paying for now.</p>

                        <div class="mt-4 rounded-lg bg-teal-50 px-4 py-3">
                            <p class="text-xs text-teal-700">Amount to collect</p>
                            <p class="text-2xl font-bold text-teal-900" x-text="money(total)"></p>
                        </div>

                        @error('item_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                        <label class="mt-4 block text-sm font-medium text-slate-700">Method</label>
                        <select name="method" x-model="method" class="{{ $input }}">
                            <option value="cash">Cash</option>
                            <option value="transfer">Bank transfer</option>
                            <option value="pos">POS</option>
                        </select>

                        <div x-show="method !== 'cash'" x-cloak>
                            <label class="mt-4 block text-sm font-medium text-slate-700">Reference</label>
                            <input name="reference" type="text" value="{{ old('reference') }}" placeholder="Transaction / session ID" class="{{ $input }}">
                        </div>

                        <button :disabled="selected.length === 0"
                                class="mt-5 w-full rounded-lg bg-teal-700 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Collect and issue receipt
                        </button>
                    </div>
                @else
                    <div class="rounded-xl border border-green-200 bg-green-50 p-5 text-sm text-green-800">
                        <p class="font-semibold">Nothing outstanding</p>
                        <p class="mt-1">New tests or prescriptions will appear here when ordered.</p>
                    </div>
                @endif

                <a href="{{ route('billing.index') }}" class="mt-4 inline-block text-sm text-slate-500 hover:underline">← Back to billing</a>
            </div>
        </div>
    </form>
</x-app-layout>