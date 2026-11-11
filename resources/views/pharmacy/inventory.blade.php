<x-app-layout>
    <x-slot name="header">Pharmacy</x-slot>

    @include('pharmacy._tabs')

    <div class="mb-6"><x-csv-transfer-actions dataset="pharmacy-catalog" label="pharmacy catalog items" /></div>

    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search drug name"
               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600 sm:max-w-xs">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="low" value="1" @checked(request()->boolean('low'))
                   class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
            Low stock only
        </label>
        <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Filter</button>
        <a href="{{ route('pharmacy.drugs.create') }}"
            class="rounded-lg border border-teal-700 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50 sm:ml-auto">
                + Add drug
        </a>
    </form>
    
   

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Drug</th>
                <th class="px-4 py-3">Price</th>
                <th class="px-4 py-3">On hand / Reserved / Available</th>
                <th class="px-4 py-3">Restock</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($drugs as $drug)
                <tr class="{{ $drug->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                    <td class="px-4 py-3">
                        <span class="font-medium">{{ $drug->display_name }}</span>
                        <span class="block text-xs text-slate-400">{{ $drug->generic_name }}</span>
                        @if ($drug->is_fluid)<span class="mt-1 inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-blue-800">Fluid prescription</span>@endif
                    </td>
                    <td class="px-4 py-3">₦{{ number_format($drug->unit_price, 2) }}</td>
                    <td class="px-4 py-3">
                        <span class="font-semibold">{{ $drug->stock_quantity }}</span>
                        <span class="block text-xs text-slate-500">{{ $drug->reserved_quantity }} reserved · {{ $drug->available_quantity }} available</span>
                        @if ($drug->is_low_stock)
                            <x-badge color="red" class="ml-2">Low</x-badge>
                        @endif
                        @if (auth()->user()->hospital?->isStandalonePharmacy())
                            @php $nextBatch = $drug->stockBatches->first(); @endphp
                            <span class="mt-1 block text-xs font-semibold text-teal-700">{{ $drug->stockBatches->filter(fn ($batch) => $batch->expiry_date->gte(today()))->sum('quantity_remaining') }} unexpired units available for sale</span>
                            @if ($nextBatch)
                                <span class="mt-1 block text-xs {{ $nextBatch->expiry_date->isPast() ? 'font-semibold text-red-700' : ($nextBatch->expiry_date->lte(today()->addDays(90)) ? 'font-semibold text-amber-700' : 'text-slate-500') }}">
                                    Earliest expiry: {{ $nextBatch->expiry_date->format('d M Y') }} · {{ $nextBatch->quantity_remaining }} units
                                </span>
                            @else
                                <span class="mt-1 block text-xs font-semibold text-red-700">No batch expiry record</span>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('pharmacy.restock', $drug) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="number" name="quantity" min="1" required placeholder="Qty"
                                class="w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @if (auth()->user()->hospital?->isStandalonePharmacy())
                                <input type="date" name="expiry_date" min="{{ now()->addDay()->toDateString() }}" required aria-label="Expiry date" class="w-36 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <input type="text" name="batch_number" placeholder="Batch #" aria-label="Batch number" class="w-28 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                            @endif
                            <button class="rounded-lg border border-teal-700 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">Add</button>
                        </form>
                    </td>
                    <td class="px-4 py-3">
                        <x-badge :color="$drug->is_active ? 'green' : 'slate'">{{ $drug->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-4">
                            <a href="{{ route('pharmacy.drugs.edit', $drug) }}" class="text-teal-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('pharmacy.drugs.toggle', $drug) }}">
                                @csrf @method('PATCH')
                                <button class="{{ $drug->is_active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                    {{ $drug->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No drugs found.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <!-- <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Drug</th>
                    <th class="px-4 py-3">Price</th>
                <th class="px-4 py-3">On hand / Reserved / Available</th>
                    <th class="px-4 py-3">Restock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($drugs as $drug)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $drug->display_name }}</span>
                            <span class="block text-xs text-slate-400">{{ $drug->generic_name }}</span>
                        </td>
                        <td class="px-4 py-3">₦{{ number_format($drug->unit_price) }}</td>
                        <td class="px-4 py-3">
                            <span class="font-semibold">{{ $drug->stock_quantity }}</span>
                            <span class="block text-xs text-slate-500">{{ $drug->reserved_quantity }} reserved · {{ $drug->available_quantity }} available</span>
                            @if ($drug->is_low_stock)
                                <x-badge color="red" class="ml-2">Low</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('pharmacy.restock', $drug) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="number" name="quantity" min="1" required placeholder="Qty"
                                       class="w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600">
                                <button class="rounded-lg border border-teal-700 px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50">Add</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No drugs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div> -->

    <div class="mt-4">{{ $drugs->links() }}</div>
</x-app-layout>
