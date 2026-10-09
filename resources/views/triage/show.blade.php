<x-app-layout>
    <x-slot name="header">Triage: {{ $visit->patient->full_name }}</x-slot>

    @php
        $patient = $visit->patient;
        $vitals  = $visit->vitals;
        $input   = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';

        $fields = [
            ['temperature',      'Temperature (°C)',   '0.1'],
            ['pulse',            'Pulse (bpm)',        '1'],
            ['bp_systolic',      'BP systolic (mmHg)', '1'],
            ['bp_diastolic',     'BP diastolic (mmHg)','1'],
            ['respiratory_rate', 'Resp. rate (/min)',  '1'],
            ['spo2',             'SpO₂ (%)',           '1'],
            ['weight',           'Weight (kg)',        '0.1'],
            ['height',           'Height (cm)',        '0.1'],
        ];
    @endphp

    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="text-sm text-slate-500">{{ ucfirst($patient->gender) }} · {{ $patient->age }} yrs</p>

        @if ($patient->allergies)
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                <strong>Allergies:</strong> {{ $patient->allergies }}
            </p>
        @endif
    </div>

    <form method="POST" action="{{ route('triage.store', $visit) }}"
          x-data="{ w: @js(old('weight', $vitals?->weight)), h: @js(old('height', $vitals?->height)) }"
          class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf

        <div class="mb-5">
            <label class="block text-sm font-medium text-slate-700">Presenting complaint</label>
            <input name="complaint" type="text" value="{{ old('complaint', $visit->complaint) }}" class="{{ $input }}">
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($fields as [$name, $label, $step])
                <div>
                    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                    <input id="{{ $name }}" name="{{ $name }}" type="number" step="{{ $step }}"
                           value="{{ old($name, $vitals?->{$name}) }}"
                           @if ($name === 'weight') x-model="w" @endif
                           @if ($name === 'height') x-model="h" @endif
                           @required(in_array($name, ['temperature', 'pulse']))
                           class="{{ $input }}">
                    @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-sm text-slate-600">
            BMI: <strong x-text="(w && h) ? (w / Math.pow(h / 100, 2)).toFixed(1) : '—'"></strong>
        </p>

        <div class="mt-5">
            <label class="block text-sm font-medium text-slate-700">Nurse's notes</label>
            <textarea name="notes" rows="2" class="{{ $input }}">{{ old('notes', $vitals?->notes) }}</textarea>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
                Save and send to doctor
            </button>
            <a href="{{ route('triage.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>