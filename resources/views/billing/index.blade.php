<x-app-layout>
    <x-slot name="header">Billing</x-slot>
    @if ($review->isNotEmpty())
        <div class="mb-8 rounded-xl border border-red-200 bg-white shadow-sm">
            <h3 class="border-b border-red-200 bg-red-50 px-5 py-3 font-semibold text-red-900">
                Online payments needing review ({{ $review->count() }})
            </h3>
            <ul class="divide-y text-sm">
                @foreach ($review as $item)
                    <li class="px-5 py-3">
                        <p class="font-medium">{{ $item->patient->full_name }}
                            <span class="ml-1 text-slate-500">₦{{ number_format($item->amount, 2) }}</span>
                        </p>
                        <p class="font-mono text-xs text-slate-400">{{ $item->reference }} · {{ $item->created_at->format('d M, g:i A') }}</p>
                        <p class="mt-1 text-xs text-red-800">{{ $item->note }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    
    {{-- Summary --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Collected today</p>
            <p class="mt-2 text-3xl font-bold text-teal-800">₦{{ number_format($collectedToday, 2) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Outstanding balance</p>
            <p class="mt-2 text-3xl font-bold text-red-700">₦{{ number_format($outstanding, 2) }}</p>
        </div>
    </div>

    {{-- Find a patient --}}
    <form method="GET" class="mb-4 flex max-w-md gap-2">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search patient name, hospital no. or phone"
               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600">
        <button class="rounded-lg bg-teal-700 px-4 text-sm font-medium text-white hover:bg-teal-800">Search</button>
    </form>

    {{-- Filters --}}
    <div class="mb-4 flex gap-2 border-b">
        @foreach ([null => 'All', 'unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid'] as $key => $label)
            <a href="{{ route('billing.index', $key ? ['status' => $key] : []) }}"
               class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $status === $key ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Paid</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">Waiting to pay</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-mono text-teal-800">{{ $invoice->invoice_number }}</span>
                            <span class="block text-xs text-slate-400">{{ $invoice->created_at->format('d M, g:i A') }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium">{{ $invoice->patient->full_name }}</td>
                        <td class="px-4 py-3 text-right">₦{{ number_format($invoice->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">₦{{ number_format($invoice->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">₦{{ number_format($invoice->balance, 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($invoice->unpaid_items_count)
                                <x-badge color="amber">{{ $invoice->unpaid_items_count }} item(s)</x-badge>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('billing.show', $invoice) }}" class="text-teal-700 hover:underline">
                                {{ $invoice->unpaid_items_count ? 'Collect' : 'View' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-app-layout>