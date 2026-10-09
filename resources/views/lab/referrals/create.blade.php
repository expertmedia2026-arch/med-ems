<x-app-layout>
    <x-slot name="header">External Lab Request</x-slot>

    @php
        $input = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label = 'block text-sm font-medium text-slate-700';
        $byCategory = $tests->groupBy(fn ($t) => $t->category ?: 'Other');
    @endphp

    <form method="POST" action="{{ route('lab.referrals.store') }}" class="max-w-3xl space-y-6"
          x-data="{
              tests: @js($tests->map(fn ($t) => ['id' => (string) $t->id, 'price' => (float) $t->price])->values()),
              selected: @js(array_map('strval', old('lab_test_ids', []))),
              get total() { return this.tests.filter(t => this.selected.includes(t.id)).reduce((s, t) => s + t.price, 0) },
              money(n) { return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
          }">
        @csrf

        <p class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900">
            For patients referred from another hospital who only need a lab test. There is no consultation or triage,
            and the patient pays for the tests only.
        </p>

        <div class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
            <div>
                <!-- <label for="hospital_number" class="{{ $label }}">Patient (hospital number or name)</label> -->
                <label for="hospital_number" class="{{ $label }}">Patient (name, hospital number or phone)</label>
                <input id="hospital_number" name="hospital_number" list="patient-list" required autocomplete="off"
                       value="{{ old('hospital_number', $selected) }}" placeholder="Start typing…" class="{{ $input }}">
                <!-- <datalist id="patient-list">
                    @foreach ($patients as $p)
                        <option value="{{ $p->hospital_number }}">{{ $p->full_name }}</option>
                    @endforeach
                </datalist> -->
                <datalist id="patient-list">
                    @foreach ($patients as $p)
                        <option value="{{ $p->hospital_number }}" label="{{ $p->full_name }}">{{ $p->full_name }}</option>
                    @endforeach
                </datalist>
                @error('hospital_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @if (auth()->user()->hasRole('admin', 'receptionist'))
                    <p class="mt-1 text-xs text-slate-500">
                        Not registered yet? <a href="{{ route('patients.create') }}" class="text-teal-700 hover:underline">Register the patient first</a>.
                    </p>
                @else
                    <p class="mt-1 text-xs text-slate-500">New patients must be registered by the front desk first.</p>
                @endif
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="referred_from" class="{{ $label }}">Referred from (hospital / clinic)</label>
                    <input id="referred_from" name="referred_from" type="text" required value="{{ old('referred_from') }}" class="{{ $input }}">
                    @error('referred_from')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="referred_by" class="{{ $label }}">Referring doctor (optional)</label>
                    <input id="referred_by" name="referred_by" type="text" value="{{ old('referred_by') }}" class="{{ $input }}">
                </div>
            </div>

            <div>
                <label for="complaint" class="{{ $label }}">Reason for the test / clinical note (optional)</label>
                <input id="complaint" name="complaint" type="text" value="{{ old('complaint') }}" class="{{ $input }}">
            </div>
        </div>

        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold">Tests requested</h3>

            @foreach ($byCategory as $category => $group)
                <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400 first:mt-0">{{ $category }}</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($group as $test)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="lab_test_ids[]" value="{{ $test->id }}" x-model="selected"
                                   class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                            {{ $test->name }} <span class="text-slate-400">₦{{ number_format($test->price) }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach

            @error('lab_test_ids')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('lab_test_ids.*')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs text-slate-500">Total to pay</p>
                <p class="text-2xl font-bold text-teal-800" x-text="money(total)"></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
                <button :disabled="selected.length === 0"
                        class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Create request
                </button>
            </div>
        </div>
    </form>
</x-app-layout>