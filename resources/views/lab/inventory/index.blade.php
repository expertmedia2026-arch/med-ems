<x-app-layout>
    <x-slot name="header">Department inventory</x-slot>

    @include('lab._tabs')

    <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        Each department maintains its own supplies here. Billable procedures and prices are managed in Department services; this page records physical stock, receipts, and usage.
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.6fr)]">
        <section class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">Add stock item</h2>
            <form method="POST" action="{{ route('lab.inventory.store') }}" class="mt-4 space-y-4">
                @csrf
                @if (auth()->user()->hasRole('admin'))
                    <label class="block text-sm font-medium text-slate-700">Department
                        <select name="department_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Select department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $selectedDepartmentId) == $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }}</p>
                @endif
                <label class="block text-sm font-medium text-slate-700">Item name
                    <input name="name" value="{{ old('name') }}" required maxlength="150" placeholder="e.g. X-ray film, contrast medium" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="block text-sm font-medium text-slate-700">Category
                    <input name="category" value="{{ old('category') }}" maxlength="80" placeholder="e.g. Consumables" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-sm font-medium text-slate-700">Unit
                        <input name="unit" value="{{ old('unit') }}" required maxlength="40" placeholder="pieces, boxes, ml" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Reorder level
                        <input name="reorder_level" type="number" min="0" step="0.001" value="{{ old('reorder_level', 0) }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <label class="block text-sm font-medium text-slate-700">Opening quantity
                    <input name="opening_quantity" type="number" min="0" step="0.001" value="{{ old('opening_quantity', 0) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <button class="w-full rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Add inventory item</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">Stock on hand</h2>
                        <p class="mt-1 text-sm text-slate-500">Record receipts and usage to keep department balances current.</p>
                    </div>
                    @if (auth()->user()->hasRole('admin'))
                        <form method="GET" class="flex gap-2">
                            <select name="department_id" class="rounded-lg border-slate-300 text-sm">
                                <option value="">All departments</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Filter</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <div class="space-y-4 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900">{{ $item->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $item->department->name }}@if ($item->category) · {{ $item->category }}@endif · {{ $item->unit }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-semibold {{ (float) $item->quantity_on_hand <= (float) $item->reorder_level ? 'text-rose-700' : 'text-slate-900' }}">{{ number_format((float) $item->quantity_on_hand, 3) }} {{ $item->unit }}</p>
                                <p class="text-xs text-slate-500">Reorder at {{ number_format((float) $item->reorder_level, 3) }}</p>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <form method="POST" action="{{ route('lab.inventory.restock', $item) }}" class="flex flex-wrap gap-2 rounded-lg bg-emerald-50 p-3">
                                @csrf
                                <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Received quantity" class="min-w-32 flex-1 rounded-lg border-emerald-200 text-sm">
                                <input name="reference" maxlength="120" placeholder="Reference" class="min-w-28 flex-1 rounded-lg border-emerald-200 text-sm">
                                <button class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Record receipt</button>
                            </form>
                            <form method="POST" action="{{ route('lab.inventory.consume', $item) }}" class="flex flex-wrap gap-2 rounded-lg bg-amber-50 p-3">
                                @csrf
                                <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Used quantity" class="min-w-32 flex-1 rounded-lg border-amber-200 text-sm">
                                <input name="note" maxlength="500" placeholder="Usage note" class="min-w-28 flex-1 rounded-lg border-amber-200 text-sm">
                                <button class="rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white">Record usage</button>
                            </form>
                        </div>
                        <details class="text-xs">
                            <summary class="cursor-pointer font-medium text-slate-600">Recent stock activity</summary>
                            <div class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
                                @forelse ($item->movements as $movement)
                                    <div class="flex flex-wrap justify-between gap-2 px-3 py-2 text-slate-600">
                                        <span>{{ ucfirst($movement->movement_type) }} {{ number_format((float) $movement->quantity, 3) }} {{ $item->unit }}@if ($movement->reference) · Ref {{ $movement->reference }}@endif @if ($movement->note) · {{ $movement->note }}@endif</span>
                                        <span>{{ $movement->recorder?->name ?? 'Staff' }} · {{ $movement->created_at->format('d M Y, g:i A') }}</span>
                                    </div>
                                @empty
                                    <p class="px-3 py-2 text-slate-500">No stock movements recorded.</p>
                                @endforelse
                            </div>
                        </details>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">No inventory items for this department yet.</p>
                @endforelse
            </div>
            <div class="border-t border-slate-200 p-4">{{ $items->links() }}</div>
        </section>
    </div>
</x-app-layout>
