@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
@endphp

<div class="grid gap-5 md:grid-cols-2">
<div>
    <label for="department_id" class="{{ $label }}">Department</label>
    <select id="department_id" name="department_id" required class="{{ $input }}">
        <option value="">Select department…</option>
        @foreach ($departments as $department)
            <option value="{{ $department->id }}" @selected(old('department_id', $consultationType?->department_id) == $department->id)>{{ $department->name }}</option>
        @endforeach
    </select>
    @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="name" class="{{ $label }}">Consultation type</label>
    <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $consultationType?->name) }}" placeholder="e.g. Dental consultation" class="{{ $input }}">
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="md:col-span-2">
    <label for="description" class="{{ $label }}">Description (optional)</label>
    <textarea id="description" name="description" rows="2" maxlength="500" class="{{ $input }}">{{ old('description', $consultationType?->description) }}</textarea>
    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="price" class="{{ $label }}">Price (₦)</label>
    <input id="price" name="price" type="number" min="0" max="99999999" step="0.01" required value="{{ old('price', $consultationType?->price) }}" class="{{ $input }}">
    @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $consultationType?->is_active ?? true)) class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
    Available for new check-ins
</label>
</div>
