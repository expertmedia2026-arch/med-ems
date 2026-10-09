<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lab report: {{ $visit->patient->full_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@media print { .no-print { display: none !important; } body { background: #fff !important; } .avoid-break { break-inside: avoid; } }</style>
</head>
<body class="bg-slate-100 py-8 font-sans text-slate-800">
    @php $patient = $visit->patient; @endphp

    <div class="mx-auto max-w-3xl rounded-xl bg-white p-10 shadow">

        {{-- Hospital header --}}
        <div class="text-center">
            @if ($hospital->logo_url)
                <img src="{{ $hospital->logo_url }}" alt="{{ $hospital->name }}" class="mx-auto mb-2 max-h-16 max-w-[12rem] object-contain">
            @else
                <p class="text-2xl">✚</p>
            @endif
            <h1 class="text-xl font-bold">{{ $hospital->name }}</h1>
            @if ($hospital->address)
                <p class="mt-1 whitespace-pre-line text-xs text-slate-500">{{ $hospital->address }}</p>
            @endif
            @php $contacts = array_filter([$hospital->phone, $hospital->email, $hospital->website]); @endphp
            @if ($contacts)
                <p class="mt-1 text-xs text-slate-500">{{ implode(' · ', $contacts) }}</p>
            @endif
            <p class="mt-4 border-t pt-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Laboratory report</p>
        </div>

        {{-- Patient and source --}}
        <dl class="mt-6 grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
            <div class="flex justify-between"><dt class="text-slate-500">Patient</dt><dd class="font-medium">{{ $patient->full_name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Hospital no.</dt><dd class="font-mono">{{ $patient->hospital_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Sex / Age</dt><dd>{{ ucfirst($patient->gender) }}, {{ $patient->age }} yrs</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ $visit->checked_in_at->format('d M Y') }}</dd></div>

            @if ($visit->isLabOnly())
                <div class="flex justify-between"><dt class="text-slate-500">Referred from</dt><dd class="font-medium">{{ $visit->referred_from }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Referring doctor</dt><dd>{{ $visit->referred_by ?: '—' }}</dd></div>
            @else
                <div class="flex justify-between"><dt class="text-slate-500">Requesting doctor</dt><dd>{{ $requests->first()->requester?->name ?? $visit->doctor?->name ?? '—' }}</dd></div>
            @endif
        </dl>

        @if ($visit->complaint)
            <p class="mt-3 text-sm text-slate-600"><span class="text-slate-500">Clinical note:</span> {{ $visit->complaint }}</p>
        @endif

        {{-- Results --}}
        <div class="mt-8 space-y-6">
            @foreach ($requests as $req)
                <div class="avoid-break border-t pt-4">
                    <h2 class="font-semibold text-teal-900">{{ $req->test->name }}</h2>

                    @if ($req->result)
                        <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 px-4 py-3 text-sm">{{ $req->result }}</p>
                    @endif

                    @foreach ($req->attachments->where('is_image', true) as $file)
                        <img src="{{ $file->url }}" alt="{{ $file->original_name }}" class="mt-3 max-h-80 rounded border object-contain">
                    @endforeach

                    @foreach ($req->attachments->where('is_image', false) as $file)
                        <p class="mt-2 text-xs text-slate-500">Attached document: {{ $file->original_name }} (available from the laboratory)</p>
                    @endforeach

                    <p class="mt-2 text-xs text-slate-400">
                        Completed {{ $req->completed_at->format('d M Y, g:i A') }}{{ $req->processor ? ' · ' . $req->processor->name : '' }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex justify-between gap-10 text-xs text-slate-500">
            <div class="flex-1 border-t pt-1">Laboratory scientist (name and signature)</div>
            <div class="flex-1 border-t pt-1">Date / stamp</div>
        </div>

        <div class="no-print mt-8 flex justify-center gap-3">
            <button onclick="window.print()" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-medium text-white hover:bg-teal-800">Print</button>
            <a href="{{ url()->previous() }}" class="px-3 py-2 text-sm text-slate-500 hover:underline">Back</a>
        </div>
    </div>
</body>
</html>