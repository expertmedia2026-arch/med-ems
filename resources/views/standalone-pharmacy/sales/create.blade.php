<x-app-layout>
    <x-slot name="header">New Pharmacy Sale</x-slot>

    @include('pharmacy._tabs')

    @php
        $saleProducts = $products->map(fn ($drug) => [
            'id' => $drug->id,
            'name' => $drug->display_name,
            'price' => (float) $drug->unit_price,
            'available' => (int) $drug->stockBatches->where('expiry_date', '>=', today())->sum('quantity_remaining'),
            'next_expiry' => $drug->stockBatches->where('expiry_date', '>=', today())->first()?->expiry_date?->format('d M Y'),
        ])->values();
    @endphp

    <form method="POST" action="{{ route('standalone-pharmacy.sales.store') }}"
          x-data="{
              products: @js($saleProducts),
              items: [{ drug_id: '', quantity: 1 }],
              discount: Number(@js(old('discount', 0))),
              product(id) { return this.products.find(product => String(product.id) === String(id)); },
              lineTotal(item) { const product = this.product(item.drug_id); return product ? product.price * Number(item.quantity || 0) : 0; },
              subtotal() { return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0); },
              total() { return Math.max(0, this.subtotal() - Number(this.discount || 0)); }
          }" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-semibold text-slate-900">Sale items</h2><p class="mt-1 text-sm text-slate-500">Only unexpired batch stock can be sold.</p></div>
                <button type="button" @click="items.push({ drug_id: '', quantity: 1 })" class="rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50">+ Add product</button>
            </div>

            <div class="mt-5 space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="grid gap-3 rounded-lg border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_7rem_8rem_auto] sm:items-start">
                        <label class="text-xs font-medium text-slate-600">Product
                            <select x-model="item.drug_id" :name="`items[${index}][drug_id]`" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                                <option value="">Select a product</option>
                                <template x-for="product in products" :key="product.id"><option :value="product.id" x-text="`${product.name} · ${product.available} available`"></option></template>
                            </select>
                            <span x-show="product(item.drug_id)" class="mt-1 block text-[11px] text-slate-500" x-text="product(item.drug_id)?.next_expiry ? `Earliest expiry ${product(item.drug_id).next_expiry}` : 'No unexpired stock batch'"></span>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Quantity
                            <input type="number" min="1" x-model.number="item.quantity" :max="product(item.drug_id)?.available || 1" :name="`items[${index}][quantity]`" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        </label>
                        <div><p class="text-xs font-medium text-slate-600">Line total</p><p class="mt-3 font-semibold text-slate-900" x-text="`₦${lineTotal(item).toLocaleString(undefined, {minimumFractionDigits: 2})}`"></p></div>
                        <button type="button" @click="items.splice(index, 1)" x-show="items.length > 1" class="mt-5 rounded-lg px-2 py-1 text-sm font-semibold text-red-600 hover:bg-red-50">Remove</button>
                    </div>
                </template>
            </div>
            @error('items')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('items.*.quantity')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <aside class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">Customer & payment</h2>
            <div class="mt-4 space-y-4">
                <label class="block text-xs font-medium text-slate-600">Customer name <span class="font-normal text-slate-400">(optional)</span><input name="customer_name" value="{{ old('customer_name') }}" maxlength="150" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="block text-xs font-medium text-slate-600">Phone <span class="font-normal text-slate-400">(optional)</span><input name="customer_phone" value="{{ old('customer_phone') }}" maxlength="30" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="block text-xs font-medium text-slate-600">Payment method
                    <select name="payment_method" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="cash">Cash</option><option value="pos">POS</option><option value="transfer">Transfer</option></select>
                </label>
                <label class="block text-xs font-medium text-slate-600">Discount (₦)<input type="number" name="discount" min="0" step="0.01" x-model.number="discount" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            </div>
            <dl class="mt-5 space-y-2 border-t border-slate-200 pt-4 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Subtotal</dt><dd class="font-semibold" x-text="`₦${subtotal().toLocaleString(undefined, {minimumFractionDigits: 2})}`"></dd></div>
                <div class="flex justify-between gap-3 text-lg"><dt class="font-semibold">Total</dt><dd class="font-bold text-teal-800" x-text="`₦${total().toLocaleString(undefined, {minimumFractionDigits: 2})}`"></dd></div>
            </dl>
            @if ($errors->any())<p class="mt-4 text-sm text-red-600">{{ $errors->first() }}</p>@endif
            <button class="mt-5 w-full rounded-lg bg-teal-800 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-teal-900">Complete sale & print receipt</button>
        </aside>
    </form>
</x-app-layout>
