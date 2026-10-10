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

    <div class="max-w-2xl space-y-6">

        {{-- Step 1 --}}
        <form method="GET" action="{{ route('portal.appointments.create') }}" class="rounded-xl border bg-white p-6 shadow-sm">
            <input type="hidden" name="type" value="{{ $type }}">

            <h2 class="font-semibold">1. Choose {{ $isLab ? 'a date' : 'a doctor and a date' }}</h2>

            <div class="mt-4 grid gap-5 {{ $isLab ? '' : 'sm:grid-cols-2' }}">
                @unless ($isLab)
                    <div>
                        <label for="doctor_id" class="{{ $label }}">Doctor</label>
                        <select id="doctor_id" name="doctor_id" required class="{{ $input }}">
                            <option value="">Select doctor…</option>
                            @foreach ($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected($doctorId === $doctor->id)>{{ $doctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless

                <div>
                    <label for="date" class="{{ $label }}">Date</label>
                    <input id="date" name="date" type="date" required value="{{ $date?->toDateString() }}"
                           min="{{ now()->toDateString() }}" max="{{ $maxDate }}" class="{{ $input }}">
                </div>
            </div>

            @error('date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

            <button class="mt-5 rounded-lg border border-teal-700 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50">
                Show available times
            </button>
        </form>

        {{-- Step 2 --}}
        @if ($slots !== null)
            <form method="POST" action="{{ route('portal.appointments.store') }}"
                  x-data="{
                      tests: @js($tests->map(fn ($t) => ['id' => (string) $t->id, 'price' => (float) $t->price])->values()),
                      selected: @js(array_map('strval', old('lab_test_ids', []))),
                      get total() { return this.tests.filter(t => this.selected.includes(t.id)).reduce((s, t) => s + t.price, 0) },
                      money(n) { return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
                  }"
                  class="rounded-xl border bg-white p-6 shadow-sm">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                @unless ($isLab)<input type="hidden" name="doctor_id" value="{{ $doctorId }}">@endunless

                <h2 class="font-semibold">2. Pick a time on {{ $date->format('l, d F Y') }}</h2>

                @if ($slots->isEmpty())
                    <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        There are no free times on this day. Please try another date.
                    </p>
                @else
                    <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4">
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
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
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

                        <p class="mt-3 text-sm text-slate-600">Estimated cost: <strong x-text="money(total)"></strong>, paid at the cashier.</p>
                    @endif

                    <div class="mt-6">
                        <label for="reason" class="{{ $label }}">{{ $isLab ? 'Note (optional)' : 'Reason for the visit (optional)' }}</label>
                        <input id="reason" name="reason" type="text" value="{{ old('reason') }}" class="{{ $input }}">
                    </div>

                    @error('doctor_id')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror

                    <button class="mt-6 rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
                        Book appointment
                    </button>
                @endif
            </form>
        @endif
    </div>
</x-portal-layout>