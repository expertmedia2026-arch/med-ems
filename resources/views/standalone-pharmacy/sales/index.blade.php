<x-app-layout>
    <x-slot name="header">Standalone Pharmacy</x-slot>

    @include('pharmacy._tabs')

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-xs font-medium text-slate-600">Start date<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 block rounded-lg border-slate-300 text-sm"></label>
        <label class="text-xs font-medium text-slate-600">End date<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 block rounded-lg border-slate-300 text-sm"></label>
        <button class="rounded-lg bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white">Run sales report</button>
        <p class="text-xs text-slate-500 sm:ml-auto">Selected period: <strong class="text-slate-700">{{ $rangeLabel }}</strong></p>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ([['Sales in period', $todaySummary['sales']], ['Items sold in period', $todaySummary['items']], ['Revenue in period', '₦'.number_format($todaySummary['revenue'], 2)]] as [$label, $value])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</p></article>
        @endforeach
    </div>

    <div class="mb-6 grid gap-5 lg:grid-cols-2">
        <section class="rounded-xl border border-red-200 bg-white shadow-sm">
            <div class="border-b border-red-100 bg-red-50 px-5 py-4"><h2 class="font-semibold text-red-900">Expired stock</h2><p class="mt-1 text-xs text-red-700">These batches are blocked from sale.</p></div>
            <ul class="divide-y divide-slate-100 text-sm">@forelse ($expiredBatches as $batch)<li class="flex justify-between gap-3 px-5 py-3"><span><strong>{{ $batch->drug->display_name }}</strong><span class="block text-xs text-slate-500">Batch {{ $batch->batch_number ?: 'not recorded' }}</span></span><span class="text-right font-semibold text-red-700">{{ $batch->quantity_remaining }} units<span class="block text-xs">Expired {{ $batch->expiry_date->format('d M Y') }}</span></span></li>@empty<li class="px-5 py-8 text-center text-slate-500">No expired stock.</li>@endforelse</ul>
        </section>
        <section class="rounded-xl border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4"><h2 class="font-semibold text-amber-900">Expiring within 90 days</h2><p class="mt-1 text-xs text-amber-700">Prioritize these batches using earliest-expiry-first sale allocation.</p></div>
            <ul class="divide-y divide-slate-100 text-sm">@forelse ($expiringSoonBatches as $batch)<li class="flex justify-between gap-3 px-5 py-3"><span><strong>{{ $batch->drug->display_name }}</strong><span class="block text-xs text-slate-500">Batch {{ $batch->batch_number ?: 'not recorded' }}</span></span><span class="text-right font-semibold text-amber-700">{{ $batch->quantity_remaining }} units<span class="block text-xs">{{ $batch->expiry_date->format('d M Y') }}</span></span></li>@empty<li class="px-5 py-8 text-center text-slate-500">No stock expiring soon.</li>@endforelse</ul>
        </section>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h2 class="font-semibold text-slate-900">Sales history</h2><p class="mt-1 text-xs text-slate-500">Completed walk-in pharmacy sales and receipts.</p></div><a href="{{ route('standalone-pharmacy.sales.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white">+ New sale</a></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Sale</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Sold by</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3 text-right">Total</th><th></th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($sales as $sale)<tr><td class="px-4 py-3"><span class="font-mono text-xs font-semibold text-teal-800">{{ $sale->sale_number }}</span><span class="block text-xs text-slate-500">{{ $sale->sold_at->format('d M Y, g:i A') }} · {{ $sale->items_count }} items</span></td><td class="px-4 py-3">{{ $sale->customer_name ?: 'Walk-in customer' }}<span class="block text-xs text-slate-500">{{ $sale->customer_phone }}</span></td><td class="px-4 py-3">{{ $sale->seller->name }}</td><td class="px-4 py-3 uppercase text-slate-600">{{ $sale->payment_method }}</td><td class="px-4 py-3 text-right font-semibold">₦{{ number_format($sale->total, 2) }}</td><td class="px-4 py-3 text-right"><a href="{{ route('standalone-pharmacy.sales.show', $sale) }}" class="font-semibold text-teal-700 hover:underline">Receipt</a></td></tr>
            @empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No retail sales recorded yet.</td></tr>@endforelse
        </tbody></table></div>
        @if ($sales->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $sales->links() }}</div>@endif
    </section>
</x-app-layout>
