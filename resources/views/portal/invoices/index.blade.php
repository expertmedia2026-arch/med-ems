<x-portal-layout>
    <x-slot name="header">Invoices & Payments</x-slot>

    <div class="mb-6 max-w-sm rounded-xl border bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Outstanding balance</p>
        <p class="mt-2 text-3xl font-bold {{ $outstanding > 0 ? 'text-red-700' : 'text-green-700' }}">₦{{ number_format($outstanding, 2) }}</p>
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">For</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Paid</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-mono text-teal-800">{{ $invoice->invoice_number }}</span>
                            <span class="block text-xs text-slate-400">{{ $invoice->created_at->format('d M Y') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($invoice->visit)
                                {{ $invoice->visit->isLabOnly() ? 'Laboratory visit' : 'Consultation' }}
                                <span class="block text-xs text-slate-400">{{ $invoice->visit->checked_in_at->format('d M Y') }}</span>
                            @else
                                Registration
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">₦{{ number_format($invoice->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">₦{{ number_format($invoice->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">₦{{ number_format($invoice->balance, 2) }}</td>
                        <td class="px-4 py-3"><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('portal.invoices.show', $invoice) }}" class="text-teal-700 hover:underline">
                                {{ $invoice->unpaid_items_count && $onlinePaymentsEnabled ? 'Pay' : 'View' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">You have no invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-portal-layout>
