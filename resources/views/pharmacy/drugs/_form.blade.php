@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
    $val   = fn ($field, $default = null) => old($field, $drug?->{$field} ?? $default);
@endphp

<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    <div>
        <label for="name" class="{{ $label }}">Brand / drug name</label>
        <input id="name" name="name" type="text" value="{{ $val('name') }}" required class="{{ $input }}">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="generic_name" class="{{ $label }}">Generic name</label>
        <input id="generic_name" name="generic_name" type="text" value="{{ $val('generic_name') }}" class="{{ $input }}">
        @error('generic_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="form" class="{{ $label }}">Form</label>
        <select id="form" name="form" class="{{ $input }}">
            <option value="">Select…</option>
            @foreach ($forms as $f)
                <option value="{{ $f }}" @selected($val('form') === $f)>{{ $f }}</option>
            @endforeach
        </select>
    </div>

    <label class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm sm:col-span-2 xl:col-span-3">
        <input type="checkbox" name="is_fluid" value="1" @checked($val('is_fluid', false)) class="mt-0.5 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
        <span><span class="font-semibold text-slate-900">This is a prescribed fluid</span><span class="mt-1 block text-xs text-slate-600">Enable this for IV fluids or oral rehydration products so nurses can select the prescription in the inpatient fluid intake chart.</span></span>
    </label>

    <div>
        <label for="strength" class="{{ $label }}">Strength</label>
        <input id="strength" name="strength" type="text" value="{{ $val('strength') }}" placeholder="e.g. 500mg or 120mg/5ml" class="{{ $input }}">
    </div>

    <div>
        <label for="unit_price" class="{{ $label }}">Unit price (₦)</label>
        <input id="unit_price" name="unit_price" type="number" step="0.01" min="0" value="{{ $val('unit_price') }}" required class="{{ $input }}">
        @error('unit_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="reorder_level" class="{{ $label }}">Reorder level (low-stock alert)</label>
        <input id="reorder_level" name="reorder_level" type="number" min="0" value="{{ $val('reorder_level', 10) }}" required class="{{ $input }}">
        @error('reorder_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    @unless ($drug)
        <div>
            <label for="stock_quantity" class="{{ $label }}">Opening stock</label>
            <input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', 0) }}" required class="{{ $input }}">
            @error('stock_quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endunless
</div>
