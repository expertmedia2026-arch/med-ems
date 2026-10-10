<x-app-layout>
    <x-slot name="header">External Lab Request</x-slot>

    @php
        $input      = 'mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600';
        $label      = 'block text-sm font-medium text-slate-700';
        $byCategory = $tests->groupBy(fn ($t) => $t->category ?: 'Other');
        $matches    = session('possible_matches', []);
    @endphp

    <form method="POST" action="{{ route('lab.referrals.store') }}" class="max-w-3xl space-y-6"
          @submit="submitting = true"
          x-data="{
              mode: @js(old('mode', 'existing')),
              hospitalNumber: @js(old('hospital_number', $selected ?? '')),
              confirmNew: '0',
              submitting: false,
              tests: @js($tests->map(fn ($t) => ['id' => (string) $t->id, 'price' => (float) $t->price])->values()),
              selected: @js(array_map('strval', old('lab_test_ids', []))),
              get total() { return this.tests.filter(t => this.selected.includes(t.id)).reduce((s, t) => s + t.price, 0) },
              money(n) { return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
              useExisting(hn) { this.hospitalNumber = hn; this.mode = 'existing'; window.scrollTo({ top: 0, behavior: 'smooth' }); }
          }">
        @csrf
        <input type="hidden" name="mode" :value="mode">
        <input type="hidden" name="confirm_new" :value="confirmNew">

        <p class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900">
            For patients who only need a lab test: referred from another hospital, or walking in on their own.
            There is no consultation or triage, and they pay for the tests only.
        </p>

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">The request could not be submitted. Please check:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Possible duplicate --}}
        @if ($matches)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-semibold">This patient may already be registered</p>
                <ul class="mt-3 divide-y divide-amber-200">
                    @foreach ($matches as $m)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div>
                                <span class="font-medium">{{ $m['name'] }}</span>
                                <span class="ml-1 font-mono text-xs">{{ $m['hospital_number'] }}</span>
                                <span class="block text-xs">{{ $m['gender'] }}, {{ $m['age'] }} yrs{{ $m['phone'] ? ' · ' . $m['phone'] : '' }}</span>
                            </div>
                            <button type="button" @click="useExisting(@js($m['hospital_number']))"
                                    class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-800">
                                Use this patient
                            </button>
                        </li>
                    @endforeach
                </ul>
                <button type="button"
                        @click="confirmNew = '1'; $nextTick(() => $el.closest('form').requestSubmit())"
                        class="mt-3 underline hover:text-amber-950">
                    None of these: create as a new patient record
                </button>
            </div>
        @endif

        {{-- Who is the patient --}}
        <div class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
            <div class="inline-flex rounded-lg border bg-slate-50 p-1 text-sm">
                <button type="button" @click="mode = 'existing'"
                        :class="mode === 'existing' ? 'bg-teal-700 text-white shadow' : 'text-slate-600'"
                        class="rounded-md px-4 py-1.5 font-medium">Existing patient</button>
                <button type="button" @click="mode = 'new'"
                        :class="mode === 'new' ? 'bg-teal-700 text-white shadow' : 'text-slate-600'"
                        class="rounded-md px-4 py-1.5 font-medium">New patient</button>
            </div>

            {{-- Existing --}}
            <div x-show="mode === 'existing'" x-cloak>
                <label for="hospital_number" class="{{ $label }}">Patient (name, hospital number or phone)</label>
                <input id="hospital_number" name="hospital_number" list="patient-list" autocomplete="off"
                       x-model="hospitalNumber" :required="mode === 'existing'" placeholder="Start typing…" class="{{ $input }}">
                <datalist id="patient-list">
                    @foreach ($patients as $p)
                        <option value="{{ $p->hospital_number }}" label="{{ $p->full_name }}">{{ $p->full_name }}</option>
                    @endforeach
                </datalist>
                @error('hospital_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- New patient --}}
            <div x-show="mode === 'new'" x-cloak class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="{{ $label }}">First name</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" :required="mode === 'new'" class="{{ $input }}">
                    @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="{{ $label }}">Last name</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" :required="mode === 'new'" class="{{ $input }}">
                    @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="gender" class="{{ $label }}">Sex</label>
                    <select id="gender" name="gender" :required="mode === 'new'" class="{{ $input }}">
                        <option value="">Select…</option>
                        <option value="male" @selected(old('gender') === 'male')>Male</option>
                        <option value="female" @selected(old('gender') === 'female')>Female</option>
                    </select>
                    @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="age" class="{{ $label }}">Age (years)</label>
                    <input id="age" name="age" type="number" min="0" max="120" value="{{ old('age') }}" :required="mode === 'new'" class="{{ $input }}">
                    @error('age')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="phone" class="{{ $label }}">Phone (recommended)</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="{{ $input }}">
                    <p class="mt-1 text-xs text-slate-500">It helps find this patient next time and avoids duplicate records.</p>
                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Source and note --}}
        <div class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="referred_from" class="{{ $label }}">Referred from (hospital / clinic)</label>
                    <input id="referred_from" name="referred_from" type="text" value="{{ old('referred_from') }}" class="{{ $input }}">
                    <p class="mt-1 text-xs text-slate-500">Leave blank if the patient came on their own.</p>
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

        {{-- Tests --}}
        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold">Tests requested</h3>

            @forelse ($byCategory as $category => $group)
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
            @empty
                <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">No active lab tests are available. Ask an administrator to check the lab test catalogue.</p>
            @endforelse

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
                <button type="submit" :disabled="submitting"
                        class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-wait disabled:opacity-60">
                    <span x-text="submitting ? 'Creating request…' : 'Create request'">Create request</span>
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
