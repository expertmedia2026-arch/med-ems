<x-app-layout>
    <x-slot name="header">A&amp;E Pharmacy</x-slot>
    <div class="mx-auto max-w-7xl space-y-6">
        @if (session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">Please review the item details.</p><ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-rose-700">Independent A&amp;E inventory</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">A&amp;E pharmacy and supplies</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">Manage medicines, injections, gauze, cotton wool, equipment and other emergency supplies here. A&amp;E controls its own quantities and prices. Items used on a patient are billed to their A&amp;E visit.</p>
        </div>

        @if ($canManage)
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">Add A&amp;E stock item</h2>
                <p class="mt-1 text-sm text-slate-500">Enter the opening quantity and the price A&amp;E should charge per unit.</p>
                <form method="POST" action="{{ route('emergency.pharmacy.items.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    @csrf
                    <div class="lg:col-span-2"><label for="ae-name" class="block text-sm font-medium text-slate-700">Item name</label><input id="ae-name" name="name" value="{{ old('name') }}" required maxlength="150" placeholder="e.g. Sterile gauze" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></div>
                    <div><label for="ae-category" class="block text-sm font-medium text-slate-700">Type</label><select id="ae-category" name="category" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"><option value="medicine">Medicine</option><option value="injection">Injection</option><option value="consumable" selected>Consumable</option><option value="equipment">Equipment</option><option value="other">Other</option></select></div>
                    <div><label for="ae-unit" class="block text-sm font-medium text-slate-700">Unit</label><input id="ae-unit" name="unit" value="{{ old('unit', 'piece') }}" required maxlength="40" placeholder="piece, pack, vial" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></div>
                    <div><label for="ae-price" class="block text-sm font-medium text-slate-700">Price / unit (₦)</label><input id="ae-price" name="unit_price" type="number" min="0" step="0.01" value="{{ old('unit_price', '0.00') }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></div>
                    <div><label for="ae-quantity" class="block text-sm font-medium text-slate-700">Opening quantity</label><input id="ae-quantity" name="quantity" type="number" min="0" value="{{ old('quantity', 0) }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></div>
                    <div><label for="ae-reorder" class="block text-sm font-medium text-slate-700">Alert when at</label><input id="ae-reorder" name="reorder_level" type="number" min="0" value="{{ old('reorder_level', 0) }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></div>
                    <div class="flex items-end"><button class="w-full rounded-lg bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Add to A&amp;E stock</button></div>
                </form>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">A&amp;E stock on hand</h2><p class="mt-1 text-sm text-slate-500">{{ $canManage ? 'Restock here and maintain local prices and reorder levels.' : 'Current A&E catalogue, stock and prices.' }}</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Item</th><th class="px-5 py-3">Stock</th><th class="px-5 py-3">Price</th><th class="px-5 py-3">Stock action</th><th class="px-5 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            <tr class="{{ !$item->is_active ? 'bg-slate-50 text-slate-400' : '' }}">
                                <td class="px-5 py-3"><span class="font-medium text-slate-900">{{ $item->name }}</span><span class="mt-1 block text-xs text-slate-500">{{ ucfirst($item->category) }} · per {{ $item->unit }}</span></td>
                                <td class="px-5 py-3"><span class="font-semibold {{ $item->quantity <= $item->reorder_level ? 'text-rose-700' : 'text-slate-800' }}">{{ $item->quantity }} {{ $item->unit }}</span>@if ($item->quantity <= $item->reorder_level)<span class="ml-2 rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700">Low</span>@endif<span class="mt-1 block text-xs text-slate-500">Alert at {{ $item->reorder_level }}</span></td>
                                <td class="px-5 py-3 font-medium">₦{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-5 py-3">
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('emergency.pharmacy.items.restock', $item) }}" class="flex items-center gap-2">@csrf<input aria-label="Quantity to add for {{ $item->name }}" type="number" name="quantity" min="1" placeholder="Add qty" required class="w-24 rounded-lg border-slate-300 text-sm shadow-sm"><button class="rounded-lg border border-teal-700 px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-50">Restock</button></form>
                                        <details class="mt-2"><summary class="cursor-pointer text-xs font-medium text-teal-700">Edit item and price</summary><form method="POST" action="{{ route('emergency.pharmacy.items.update', $item) }}" class="mt-2 grid gap-2 sm:grid-cols-2">@csrf @method('PUT')<input aria-label="Item name" name="name" value="{{ $item->name }}" required maxlength="150" class="rounded-lg border-slate-300 text-xs"><select aria-label="Item type" name="category" class="rounded-lg border-slate-300 text-xs">@foreach (['medicine', 'injection', 'consumable', 'equipment', 'other'] as $category)<option value="{{ $category }}" @selected($item->category === $category)>{{ ucfirst($category) }}</option>@endforeach</select><input aria-label="Unit" name="unit" value="{{ $item->unit }}" required maxlength="40" class="rounded-lg border-slate-300 text-xs"><input aria-label="Price per unit" name="unit_price" type="number" min="0" step="0.01" value="{{ $item->unit_price }}" required class="rounded-lg border-slate-300 text-xs"><input aria-label="Reorder alert level" name="reorder_level" type="number" min="0" value="{{ $item->reorder_level }}" required class="rounded-lg border-slate-300 text-xs"><button class="rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white">Save item</button></form></details>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($canManage)<form method="POST" action="{{ route('emergency.pharmacy.items.status', $item) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}"><button class="text-xs font-semibold {{ $item->is_active ? 'text-rose-700' : 'text-teal-700' }}">{{ $item->is_active ? 'Deactivate' : 'Activate' }}</button></form>@else{{ $item->is_active ? 'Active' : 'Inactive' }}@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No A&amp;E catalogue items yet.@if ($canManage) Add the unit stock A&amp;E keeps above. @endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())<div class="border-t px-5 py-3">{{ $items->links() }}</div>@endif
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Recent A&amp;E stock activity</h2><p class="mt-1 text-sm text-slate-500">Opening stock, restocks and items used for patients.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Item</th><th class="px-5 py-3">Activity</th><th class="px-5 py-3">Stock change</th><th class="px-5 py-3">Patient / Visit</th><th class="px-5 py-3">Staff</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse ($movements as $movement)<tr><td class="whitespace-nowrap px-5 py-3">{{ $movement->created_at->format('d M Y, g:i A') }}</td><td class="px-5 py-3 font-medium">{{ $movement->item?->name ?? 'A&E item' }}</td><td class="px-5 py-3">{{ str($movement->type)->replace('_', ' ')->title() }}<span class="block max-w-md text-xs text-slate-500">{{ $movement->notes }}</span></td><td class="px-5 py-3">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }} <span class="text-xs text-slate-500">({{ $movement->stock_before }} → {{ $movement->stock_after }})</span></td><td class="px-5 py-3">{{ $movement->visit?->patient?->full_name ?? '—' }}</td><td class="px-5 py-3">{{ $movement->user?->name ?? 'System' }}</td></tr>
                @empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No A&amp;E stock activity recorded yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>
        <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">A&amp;E catalogue items and prices are managed independently from main Pharmacy. If A&amp;E does not stock a medicine, create a normal prescription for the main Pharmacy to dispense.</p>
    </div>
</x-app-layout>
