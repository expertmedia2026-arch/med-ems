<x-app-layout>
    <x-slot name="header">{{ $labRequest->test->name }}</x-slot>

    @php
        $visit   = $labRequest->visit;
        $patient = $visit->patient;

        // Locked only when this result is already entered AND the visit is closed
        $locked  = $labRequest->status->value === 'completed' && $visit->status->value === 'completed';
    @endphp

    {{-- Patient --}}
    <div class="mb-6 rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-sm text-teal-700">{{ $patient->hospital_number }}</p>
                <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
                <p class="text-sm text-slate-500">
                    {{ ucfirst($patient->gender) }} · {{ $patient->dob_estimated ? '~' : '' }}{{ $patient->age }} yrs
                    · Requested by {{ $labRequest->requester->name }}, {{ $labRequest->created_at->format('d M, g:i A') }}
                </p>
            </div>
            <x-badge :color="$labRequest->status->value === 'pending' ? 'amber' : 'green'">{{ $labRequest->status->label() }}</x-badge>
        </div>

        @if ($visit->complaint && ! $visit->isLabOnly())
            <p class="mt-3 text-sm text-slate-600">Clinical note: “{{ $visit->complaint }}”</p>
        @endif
    </div>

    {{-- Referral / walk-in info --}}
    @if ($visit->isLabOnly())
        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">
            <p class="font-semibold">{{ $visit->referred_from ? 'External referral' : 'Walk-in lab client' }}</p>

            @if ($visit->referred_from)
                <p class="mt-1">
                    Referred from <strong>{{ $visit->referred_from }}</strong>
                    @if ($visit->referred_by) by {{ $visit->referred_by }}@endif.
                </p>
            @endif

            @if ($visit->complaint)
                <p class="mt-1">Reason: {{ $visit->complaint }}</p>
            @endif

            @if ($canCancel)
                <form method="POST" action="{{ route('lab.referrals.destroy', $visit) }}" class="mt-3"
                      onsubmit="return confirm('Cancel this whole request? This removes its tests and invoice.')">
                    @csrf @method('DELETE')
                    <button class="text-red-600 hover:underline">Cancel this request</button>
                </form>
            @endif
        </div>
    @endif

    {{-- Print report --}}
    @if ($labRequest->status->value === 'completed')
        <div class="mb-6">
            <a href="{{ route('lab.report', $visit) }}" target="_blank"
               class="inline-block rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                Print report
            </a>
            <span class="ml-2 text-xs text-slate-500">Includes every completed test on this visit.</span>
        </div>
    @endif

    {{-- Result --}}
    <div class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">

        @if ($canProcess && $locked)
            {{-- Result entered and visit closed: read-only --}}
            <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700">
                <p class="font-semibold">Visit completed</p>
                <p class="mt-1">This result is read-only and can no longer be updated.</p>
            </div>

            <h3 class="mb-2 text-sm font-medium text-slate-700">Result</h3>

            @if ($labRequest->result)
                <p class="whitespace-pre-line rounded-lg bg-slate-50 px-4 py-3 text-sm">{{ $labRequest->result }}</p>
            @endif

            @include('lab._attachments', ['attachments' => $labRequest->attachments, 'canManage' => false])

            <p class="mt-3 text-xs text-slate-500">
                Processed by {{ $labRequest->processor?->name }} on {{ $labRequest->completed_at->format('d M Y, g:i A') }}
            </p>

            <a href="{{ route('lab.index') }}" class="mt-5 inline-block text-sm text-teal-700 hover:underline">Back</a>

        @elseif ($canProcess && ($labRequest->is_paid || $visit->is_emergency))
            {{-- Paid tests and urgent ER tests can be entered or updated. --}}
            @if ($visit->is_emergency && ! $labRequest->is_paid)
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">
                    <p class="font-semibold">Emergency care · process without upfront payment</p>
                    <p class="mt-1">The test charge remains outstanding on the patient’s ER invoice for the billing desk.</p>
                </div>
            @endif
            @if ($labRequest->attachments->isNotEmpty())
                <h3 class="text-sm font-medium text-slate-700">Attached files ({{ $labRequest->attachments->count() }})</h3>
                @include('lab._attachments', ['attachments' => $labRequest->attachments, 'canManage' => true])
                <hr class="my-6">
            @endif

            <form method="POST" action="{{ route('lab.update', $labRequest) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <label for="result" class="block text-sm font-medium text-slate-700">Result / report</label>
                <textarea id="result" name="result" rows="6"
                          placeholder="e.g. Malaria parasite: positive (++)"
                          class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-600 focus:ring-teal-600">{{ old('result', $labRequest->result) }}</textarea>
                @error('result')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                <label for="files" class="mt-5 block text-sm font-medium text-slate-700">
                    {{ $labRequest->attachments->isNotEmpty() ? 'Add more images or files' : 'Attach images or files (X-ray, scan, report)' }}
                </label>
                <input id="files" name="files[]" type="file" multiple accept="image/png,image/jpeg,image/webp,application/pdf"
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-teal-700 hover:file:bg-teal-100">
                <p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP or PDF. Up to 10 files, 10 MB each. You can save with a result, files, or both.</p>
                @error('files')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @foreach ($errors->get('files.*') as $messages)
                    @foreach ($messages as $message)
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @endforeach
                @endforeach

                <div class="mt-5 flex items-center gap-3">
                    <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">
                        {{ $labRequest->status->value === 'completed' ? 'Update result' : 'Save result' }}
                    </button>
                    <a href="{{ route('lab.index') }}" class="text-sm text-slate-500 hover:underline">Back</a>
                </div>
            </form>

        @elseif ($canProcess)
            {{-- Routine care: not paid yet --}}
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <p class="font-semibold">Payment required</p>
                <p class="mt-1">
                    This test (₦{{ number_format($labRequest->invoiceItem?->total ?? 0, 2) }}) has not been paid for.
                    Results can be entered once the patient has paid at the billing desk.
                </p>
                <a href="{{ route('lab.index') }}" class="mt-3 inline-block text-teal-700 hover:underline">Back</a>
            </div>

        @else
            {{-- Doctors: read-only --}}
            <h3 class="mb-2 text-sm font-medium text-slate-700">Result</h3>

            @if ($labRequest->result)
                <p class="whitespace-pre-line rounded-lg bg-slate-50 px-4 py-3 text-sm">{{ $labRequest->result }}</p>
            @endif

            @include('lab._attachments', ['attachments' => $labRequest->attachments, 'canManage' => false])

            @if ($labRequest->status->value === 'completed')
                <p class="mt-3 text-xs text-slate-500">
                    Processed by {{ $labRequest->processor?->name }} on {{ $labRequest->completed_at->format('d M Y, g:i A') }}
                </p>
            @else
                <p class="text-sm text-slate-500">Awaiting result from the lab.</p>
            @endif

            <a href="{{ route('lab.index') }}" class="mt-5 inline-block text-sm text-teal-700 hover:underline">Back</a>
        @endif
    </div>
</x-app-layout>
