@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
    $val   = fn ($field) => old($field, $test?->{$field});
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="department_id" class="{{ $label }}">Department</label>
        @if (auth()->user()->hasRole('admin'))
            <select id="department_id" name="department_id" required class="{{ $input }}">
                <option value="">Select department</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $selectedDepartmentId) === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
            <p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $departments->firstWhere('id', $selectedDepartmentId)?->name }}</p>
        @endif
        @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="name" class="{{ $label }}">Service or procedure name</label>
        <input id="name" name="name" type="text" value="{{ $val('name') }}" required class="{{ $input }}">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="code" class="{{ $label }}">Code (optional)</label>
        <input id="code" name="code" type="text" value="{{ $val('code') }}" placeholder="e.g. FBC" class="{{ $input }} uppercase">
        @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="price" class="{{ $label }}">Price (₦)</label>
        <input id="price" name="price" type="number" step="0.01" min="0" value="{{ $val('price') }}" required class="{{ $input }}">
        @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="category" class="{{ $label }}">Category</label>
        <input id="category" name="category" type="text" list="category-list" value="{{ $val('category') }}" class="{{ $input }}">
        <datalist id="category-list">
            @foreach ($categories as $cat)<option value="{{ $cat }}">@endforeach
        </datalist>
    </div>

    <div>
        <label for="sample_type" class="{{ $label }}">Sample type</label>
        <select id="sample_type" name="sample_type" class="{{ $input }}">
            <option value="">Select…</option>
            @foreach ($samples as $s)
                <option value="{{ $s }}" @selected($val('sample_type') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="turnaround_hours" class="{{ $label }}">Turnaround time (hours)</label>
        <input id="turnaround_hours" name="turnaround_hours" type="number" min="1" value="{{ $val('turnaround_hours') }}" class="{{ $input }}">
        @error('turnaround_hours')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="{{ $label }}">Description / preparation notes</label>
        <textarea id="description" name="description" rows="3" placeholder="e.g. Fasting for 8 hours required" class="{{ $input }}">{{ $val('description') }}</textarea>
    </div>
</div>
