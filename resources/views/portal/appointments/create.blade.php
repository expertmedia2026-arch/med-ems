<x-portal-layout>
    <x-slot name="header">Book an Appointment</x-slot>

    @php
        $isLab = $type === 'lab';
        $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    <a href="{{ route('portal.appointments.index') }}" class="mb-4 inline-block text-sm text-teal-700 hover:underline">← Back to appointments</a>

    {{-- Type --}}
    <div class="mb-6 inline-flex rounded-lg border bg-white p-1 text-sm">
        <a href="{{ route('portal.appointments.create', ['type' => 'consultation']) }}"
           class="rounded-md px-4 py-1.5 font-medium {{ ! $isLab ? 'bg-teal-700 text-white shadow' : 'text-slate-600' }}">See a doctor</a>
        <a href="{{ route('portal.appointments.create', ['type' => 'lab']) }}"
           class="rounded-md px-4 py-1.5 font-medium {{ $isLab ? 'bg-teal-700 text-white shadow' : 'text-slate-600' }}">Lab test</a>
    </div>

    <div class="w-full space-y-6">

        {{-- Step 1 --}}
        <form method="GET" action="{{ route('portal.appointments.create') }}" class="rounded-xl border bg-white p-4 shadow-sm sm:p-6 lg:p-8">
            <input type="hidden" name="type" value="{{ $type }}">

            <h2 class="font-semibold">1. Choose {{ $isLab ? 'a date' : 'a department, doctor and date' }}</h2>

            <div class="mt-4 grid gap-5 {{ $isLab ? 'md:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3' }}">
                @unless ($isLab)
                    <div>
                        <label for="department_id" class="{{ $label }}">Department</label>
                        <select id="department_id" name="department_id" required class="{{ $input }}">
                            <option value="">Select department…</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="doctor_id" class="{{ $label }}">Doctor</label>
                        <select id="doctor_id" name="doctor_id" required class="{{ $input }}">
                            <option value="">Select a department first…</option>
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" data-department="{{ $doctor->department_id }}" @selected($doctorId === $doctor->id)>{{ $doctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless

                <div>
                    <label for="date" class="{{ $label }}">Date</label>
                    @if ($isLab)
                        <input id="date" name="date" type="date" required value="{{ $date?->toDateString() }}"
                               min="{{ now()->toDateString() }}" max="{{ $maxDate }}" class="{{ $input }}">
                    @else
                        <select id="date" name="date" required class="{{ $input }}">
                            <option value="">Choose a doctor first…</option>
                        </select>
                        <p id="availability_note" class="mt-1 text-xs text-slate-500">Choose a doctor to see available dates.</p>
                    @endif
                </div>
            </div>

            @error('date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="mt-5 flex justify-end border-t border-slate-200 pt-5">
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-teal-700 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-50 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                    {{ $isLab ? 'Show available times' : 'Continue' }}
                </button>
            </div>
        </form>

        @unless ($isLab)
            <script>
                (() => {
                    const datesByDoctor = @js($availabilityByDoctor);
                    const departmentSelect = document.getElementById('department_id');
                    const doctorSelect = document.getElementById('doctor_id');
                    const dateSelect = document.getElementById('date');
                    const note = document.getElementById('availability_note');
                    const previousDate = @js($date?->toDateString());

                    const previousDoctor = @js($doctorId);
                    const populateDoctors = (selectedDoctor = '') => {
                        [...doctorSelect.options].slice(1).forEach((option) => {
                            option.hidden = option.dataset.department !== departmentSelect.value;
                        });
                        const selectedOption = [...doctorSelect.options].find((option) => option.value === String(selectedDoctor) && !option.hidden);
                        doctorSelect.value = selectedOption ? selectedDoctor : '';
                        populateDates();
                    };

                    const populateDates = (selectedDate = '') => {
                        const dates = datesByDoctor[doctorSelect.value] ?? [];
                        dateSelect.replaceChildren(new Option(dates.length ? 'Choose an available date…' : 'No available dates', ''));

                        dates.forEach((date) => {
                            const label = new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
                                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                            });
                            dateSelect.add(new Option(label, date, false, date === selectedDate));
                        });

                        note.textContent = dates.length
                            ? 'Choose a date the doctor has marked as available.'
                            : 'This doctor has not marked any upcoming dates as available.';
                    };

                    departmentSelect.addEventListener('change', () => populateDoctors());
                    doctorSelect.addEventListener('change', () => populateDates());
                    populateDoctors(previousDoctor);
                    populateDates(previousDate);
                })();
            </script>
        @endunless

        @if (! $isLab && $date && $doctorId && $departmentId)
            <form method="POST" action="{{ route('portal.appointments.store') }}" class="rounded-xl border bg-white p-4 shadow-sm sm:p-6 lg:p-8">
                @csrf
                <input type="hidden" name="type" value="consultation">
                <input type="hidden" name="department_id" value="{{ $departmentId }}">
                <input type="hidden" name="doctor_id" value="{{ $doctorId }}">
                <input type="hidden" name="appointment_date" value="{{ $date->toDateString() }}">

                <h2 class="font-semibold">2. Confirm your booking</h2>
                <p class="mt-3 text-sm text-slate-600">{{ $doctors->firstWhere('id', $doctorId)?->name }} · {{ $departments->firstWhere('id', $departmentId)?->name }} · {{ $date->format('l, d F Y') }}</p>
                <p class="mt-4 rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-900">This booking reserves a date only. There is no appointment time; patients are seen in the order they arrive at the hospital.</p>

                <div class="mt-4">
                    <label for="reason" class="{{ $label }}">Reason for the visit (optional)</label>
                    <input id="reason" name="reason" type="text" value="{{ old('reason') }}" class="{{ $input }}">
                </div>

                @error('appointment_date')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('doctor_id')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="mt-6 flex justify-end border-t border-slate-200 pt-5">
                    <button class="inline-flex min-h-11 min-w-40 items-center justify-center rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Book appointment</button>
                </div>
            </form>
        @endif

        {{-- Lab bookings keep their timed slot flow. --}}
        @if ($slots !== null)
            <form method="POST" action="{{ route('portal.appointments.store') }}"
                  x-data="{
                      tests: @js($tests->map(fn ($t) => ['id' => (string) $t->id, 'price' => (float) $t->price])->values()),
                      selected: @js(array_map('strval', old('lab_test_ids', []))),
                      get total() { return this.tests.filter(t => this.selected.includes(t.id)).reduce((s, t) => s + t.price, 0) },
                      money(n) { return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
                  }"
                  class="rounded-xl border bg-white p-4 shadow-sm sm:p-6 lg:p-8">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                @unless ($isLab)<input type="hidden" name="doctor_id" value="{{ $doctorId }}">@endunless

                <h2 class="font-semibold">2. Pick a time on {{ $date->format('l, d F Y') }}</h2>

                @if ($slots->isEmpty())
                    <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        There are no free times on this day. Please try another date.
                    </p>
                @else
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8">
                        @foreach ($slots as $slot)
                            <label class="cursor-pointer">
                                <input type="radio" name="slot" value="{{ $slot->format('Y-m-d H:i') }}" required class="peer sr-only">
                                <span class="block rounded-lg border px-3 py-2 text-center text-sm hover:bg-slate-50 peer-checked:border-teal-700 peer-checked:bg-teal-700 peer-checked:text-white">
                                    {{ $slot->format('g:i A') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif

                @error('slot')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror

                @if ($slots->isNotEmpty())
                    @if ($isLab)
                        <h3 class="mt-6 text-sm font-medium text-slate-700">Tests</h3>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($tests as $test)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="lab_test_ids[]" value="{{ $test->id }}" x-model="selected"
                                           class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                                    {{ $test->name }} <span class="text-slate-400">₦{{ number_format($test->price) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('lab_test_ids')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        @error('lab_test_ids.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                        <p class="mt-3 text-sm text-slate-600">Estimated cost: <strong x-text="money(total)"></strong>, paid at the billing desk.</p>
                    @endif

                    <div class="mt-6">
                        <label for="reason" class="{{ $label }}">{{ $isLab ? 'Note (optional)' : 'Reason for the visit (optional)' }}</label>
                        <input id="reason" name="reason" type="text" value="{{ old('reason') }}" class="{{ $input }}">
                    </div>

                    @error('doctor_id')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror

                    <div class="mt-6 flex justify-end border-t border-slate-200 pt-5">
                        <button class="inline-flex min-h-11 min-w-40 items-center justify-center rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                            Book appointment
                        </button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-portal-layout>
