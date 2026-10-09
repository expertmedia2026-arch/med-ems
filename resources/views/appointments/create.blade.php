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
            <label for="doctor_id" class="{{ $label }}">Doctor</label>
            <select id="doctor_id" name="doctor_id" required class="{{ $input }}">
                <option value="">Select doctor…</option>
                @foreach ($doctors as $doctor)
                    <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
                @endforeach
            </select>
            @error('doctor_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="scheduled_at" class="{{ $label }}">Date and time</label>
            <input id="scheduled_at" name="scheduled_at" type="datetime-local" required
                   value="{{ old('scheduled_at') }}" min="{{ now()->format('Y-m-d\T00:00') }}" class="{{ $input }}">
            @error('scheduled_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reason" class="{{ $label }}">Reason</label>
            <input id="reason" name="reason" type="text" value="{{ old('reason') }}" class="{{ $input }}">
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Book appointment</button>
            <a href="{{ route('appointments.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>