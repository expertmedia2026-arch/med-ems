@if ($visit->is_emergency)
    @php
        $canRecordUse = auth()->user()->canAccessEmergency()
            && auth()->user()->hasRole('admin', 'nurse', 'doctor')
            && in_array($visit->status, [\App\Enums\VisitStatus::WaitingTriage, \App\Enums\VisitStatus::WaitingDoctor, \App\Enums\VisitStatus::InConsultation], true);
        $itemInput = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-600 focus:ring-teal-600';
    @endphp
    <section class="overflow-hidden rounded-xl border border-rose-100 bg-white shadow-sm">
        @if (session('success'))<div class="border-b border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800 sm:px-7">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm text-red-800 sm:px-7"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="border-b border-rose-100 px-5 py-4 sm:px-7">
            <h2 class="font-semibold text-slate-900">A&amp;E pharmacy · items used</h2>
            <p class="mt-1 text-sm text-slate-500">Record medicines or supplies taken from A&amp;E stock. For medicines not stocked here, use the regular prescription and main Pharmacy workflow.</p>
        </div>

        @if ($canRecordUse)
            <form method="POST" action="{{ route('emergency.items-used.store', $visit) }}" class="grid gap-4 bg-slate-50 p-5 sm:grid-cols-2 sm:px-7">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700" for="emergency-item-id">A&amp;E stock item</label>
                    <select id="emergency-item-id" name="emergency_pharmacy_item_id" required class="{{ $itemInput }}">
                        <option value="">Select item…</option>
                        @foreach ($emergencyStockItems as $item)
                            <option value="{{ $item->id }}" @selected(old('emergency_pharmacy_item_id') == $item->id)>{{ $item->name }} · {{ ucfirst($item->category) }} · {{ $item->quantity }} {{ $item->unit }} available · ₦{{ number_format((float) $item->unit_price, 2) }} / {{ $item->unit }}</option>
                        @endforeach
                    </select>
                    @error('emergency_pharmacy_item_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @if ($emergencyStockItems->isEmpty())<p class="mt-1 text-xs text-amber-700">No active A&amp;E items are currently in stock. Use the normal prescription workflow for medicines supplied by main Pharmacy.</p>@endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="emergency-quantity">Quantity used</label>
                    <input id="emergency-quantity" name="quantity" type="number" min="1" max="100000" value="{{ old('quantity', 1) }}" required class="{{ $itemInput }}">
                    @error('quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="emergency-dose">Dose / amount <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="emergency-dose" name="dose_or_amount" value="{{ old('dose_or_amount') }}" maxlength="100" placeholder="Dose given or amount used" class="{{ $itemInput }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700" for="emergency-item-notes">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="emergency-item-notes" name="notes" value="{{ old('notes') }}" maxlength="500" class="{{ $itemInput }}">
                </div>
                <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">Saving records the staff member and time, deducts A&amp;E stock, and adds the item charge to this visit’s invoice.</p>
                    <button @disabled($emergencyStockItems->isEmpty()) class="rounded-lg bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50">Record item used</button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Recorded</th><th class="px-5 py-3">Item</th><th class="px-5 py-3">Quantity</th><th class="px-5 py-3">Recorded by</th><th class="px-5 py-3">Charge</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($visit->emergencyItemUses as $use)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3">{{ $use->created_at->format('d M, g:i A') }}</td>
                            <td class="px-5 py-3 font-medium">{{ $use->item?->name ?? 'A&E item' }}@if ($use->dose_or_amount)<span class="block text-xs font-normal text-slate-500">{{ $use->dose_or_amount }}</span>@endif</td>
                            <td class="px-5 py-3">{{ $use->quantity }}</td>
                            <td class="px-5 py-3">{{ $use->recorder?->name ?? '—' }}</td>
                            <td class="px-5 py-3">₦{{ number_format((float) ($use->invoiceItem?->total ?? ($use->unit_price * $use->quantity)), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No A&amp;E stock items recorded for this visit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
