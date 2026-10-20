@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
@endphp

<div>
    <label for="name" class="{{ $label }}">Department name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $department?->name) }}" required maxlength="120" class="{{ $input }}">
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="mt-5">
    <label for="description" class="{{ $label }}">Description <span class="font-normal text-slate-400">(optional)</span></label>
    <textarea id="description" name="description" rows="3" maxlength="500" class="{{ $input }}">{{ old('description', $department?->description) }}</textarea>
    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
    <input type="hidden" name="has_ward" value="0">
    <label class="flex items-start gap-3">
        <input type="checkbox" name="has_ward" value="1" @checked((bool) old('has_ward', $department?->has_ward ?? false)) class="mt-0.5 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
        <span>
            <span class="block text-sm font-medium text-slate-800">This department admits inpatients</span>
            <span class="mt-1 block text-xs text-slate-500">Only departments with a ward can be selected for hospital admission.</span>
        </span>
    </label>
    @error('has_ward')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
