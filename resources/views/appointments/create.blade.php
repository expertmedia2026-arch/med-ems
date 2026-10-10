<x-app-layout>
    <x-slot name="header">Book Appointment</x-slot>

    @php
        $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <form method="POST" action="{{ route('appointments.store') }}" class="max-w-2xl space-y-5 rounded-xl border bg-white p-6 shadow-sm">
        @csrf

        <div>
            <label for="hospital_number" class="{{ $label }}">Patient (hospital number or name)</label>
            <input id="hospital_number" name="hospital_number" list="patient-list" required autocomplete="off"
                   value="{{ old('hospital_number', $selected) }}" placeholder="Start typing…" class="{{ $input }}">
            <datalist id="patient-list">
                @foreach ($patients as $p)
                    <option value="{{ $p->hospital_number }}">{{ $p->full_name }}</option>
                @endforeach
            </datalist>
            @error('hospital_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="department_id" class="{{ $label }}">Department</label>
            <select id="department_id" name="department_id" required class="{{ $input }}">
                <option value="">Select department…</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $selectedDepartment) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="doctor_id" class="{{ $label }}">Doctor</label>
            <select id="doctor_id" name="doctor_id" required class="{{ $input }}">
                <option value="">Select a department first…</option>
                @foreach ($doctors as $doctor)
                    <option value="{{ $doctor->id }}" data-department="{{ $doctor->department_id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
                @endforeach
            </select>
            @error('doctor_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="appointment_date" class="{{ $label }}">Available date</label>
            <select id="appointment_date" name="appointment_date" required class="{{ $input }}">
                <option value="">Choose a doctor first…</option>
            </select>
            <p id="availability_note" class="mt-1 text-xs text-slate-500">Choose a doctor to see their available dates.</p>
            @error('appointment_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <p class="rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-900">This booking reserves a date only. Patients will be seen in the order they arrive at the hospital.</p>

        <div>
            <label for="reason" class="{{ $label }}">Reason</label>
            <input id="reason" name="reason" type="text" value="{{ old('reason') }}" class="{{ $input }}">
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Book appointment</button>
            <a href="{{ route('appointments.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>

    <script>
        (() => {
            const datesByDoctor = @js($availabilityByDoctor);
            const departmentSelect = document.getElementById('department_id');
            const doctorSelect = document.getElementById('doctor_id');
            const dateSelect = document.getElementById('appointment_date');
            const note = document.getElementById('availability_note');
            const previousDate = @js(old('appointment_date'));

            const previousDoctor = @js(old('doctor_id'));
            const populateDoctors = (selectedDoctor = '') => {
                const departmentId = departmentSelect.value;
                [...doctorSelect.options].slice(1).forEach((option) => {
                    option.hidden = option.dataset.department !== departmentId;
                });
                const selectedOption = [...doctorSelect.options].find((option) => option.value === selectedDoctor && !option.hidden);
                doctorSelect.value = selectedOption ? selectedDoctor : '';
                populateDates();
            };

            const populateDates = (selectedDate = '') => {
                const dates = datesByDoctor[doctorSelect.value] ?? [];
                dateSelect.replaceChildren(new Option(dates.length ? 'Select an available date…' : 'No available dates', ''));

                dates.forEach((date) => {
                    const label = new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
                        weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                    });
                    dateSelect.add(new Option(label, date, false, date === selectedDate));
                });

                note.textContent = dates.length
                    ? 'Only dates marked available by this doctor can be booked.'
                    : 'This doctor has not marked any upcoming dates as available.';
            };

            departmentSelect.addEventListener('change', () => populateDoctors());
            doctorSelect.addEventListener('change', () => populateDates());
            populateDoctors(previousDoctor);
            populateDates(previousDate);
        })();
    </script>
</x-app-layout>
