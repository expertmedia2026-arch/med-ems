@php
    $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
    $label = 'block text-sm font-medium text-slate-700';
    $val   = fn ($field) => old($field, $patient?->{$field});
    $dob   = old('date_of_birth', $patient?->date_of_birth?->format('Y-m-d'));
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    @foreach ([['first_name', 'First name', 'text'], ['last_name', 'Last name', 'text']] as [$name, $text, $type])
        <div>
            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val($name) }}" required class="{{ $input }}">
            @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <div>
        <label for="gender" class="{{ $label }}">Gender</label>
        <select id="gender" name="gender" required class="{{ $input }}">
            <option value="">Select…</option>
            @foreach (['male' => 'Male', 'female' => 'Female'] as $v => $t)
                <option value="{{ $v }}" @selected($val('gender') === $v)>{{ $t }}</option>
            @endforeach
        </select>
        @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_of_birth" class="{{ $label }}">Date of birth</label>
        <input id="date_of_birth" name="date_of_birth" type="date" value="{{ $dob }}" max="{{ today()->toDateString() }}" required class="{{ $input }}">
        @error('date_of_birth')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input name="is_nhis" type="hidden" value="0">
            <input id="is_nhis" name="is_nhis" type="checkbox" value="1" @checked((bool) old('is_nhis', $patient?->is_nhis)) class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
            Patient is enrolled with NHIS
        </label>
        @error('is_nhis')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

        <div id="nhis_number_field" class="mt-4" @if (!old('is_nhis', $patient?->is_nhis)) hidden @endif>
            <label for="nhis_number" class="{{ $label }}">NHIS number</label>
            <input id="nhis_number" name="nhis_number" type="text" value="{{ $val('nhis_number') }}" maxlength="50" class="{{ $input }}">
            @error('nhis_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @foreach ([['phone', 'Phone', 'tel'], ['email', 'Email', 'email']] as [$name, $text, $type])
        <div>
            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val($name) }}" class="{{ $input }}">
            @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <div class="sm:col-span-2">
        <label for="address" class="{{ $label }}">Address</label>
        <textarea id="address" name="address" rows="2" class="{{ $input }}">{{ $val('address') }}</textarea>
        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="blood_group" class="{{ $label }}">Blood group</label>
        <select id="blood_group" name="blood_group" class="{{ $input }}">
            <option value="">Unknown</option>
            @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                <option value="{{ $bg }}" @selected($val('blood_group') === $bg)>{{ $bg }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="genotype" class="{{ $label }}">Genotype</label>
        <select id="genotype" name="genotype" class="{{ $input }}">
            <option value="">Unknown</option>
            @foreach (['AA', 'AS', 'SS', 'AC', 'SC'] as $g)
                <option value="{{ $g }}" @selected($val('genotype') === $g)>{{ $g }}</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label for="allergies" class="{{ $label }}">Known allergies</label>
        <input id="allergies" name="allergies" type="text" value="{{ $val('allergies') }}" placeholder="e.g. Penicillin" class="{{ $input }}">
    </div>

    @foreach ([['next_of_kin_name', 'Next of kin', 'text'], ['next_of_kin_phone', 'Next of kin phone', 'tel']] as [$name, $text, $type])
        <div>
            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val($name) }}" class="{{ $input }}">
        </div>
    @endforeach
</div>

<script>
    (() => {
        const enrolled = document.getElementById('is_nhis');
        const numberField = document.getElementById('nhis_number_field');
        const numberInput = document.getElementById('nhis_number');

        const updateNhisField = () => {
            numberField.hidden = !enrolled.checked;
            numberInput.required = enrolled.checked;
        };

        enrolled.addEventListener('change', updateNhisField);
        updateNhisField();
    })();
</script>
