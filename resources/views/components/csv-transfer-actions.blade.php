@props(['dataset', 'label'])

<section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Import or export {{ $label }}</h2>
            <p class="mt-1 text-xs text-slate-500">CSV only, up to 10 MB. Existing matching records are skipped; imports do not overwrite records.</p>
            @if ($dataset === 'staff')
                <p class="mt-1 text-xs text-slate-500">Imported staff receive a 7-day account invitation by email. Passwords are never imported.</p>
            @elseif ($dataset === 'patients')
                <p class="mt-1 text-xs text-slate-500">Imported patients are treated as existing registrations, so no new registration fee is created.</p>
            @elseif ($dataset === 'pharmacy-catalog')
                <p class="mt-1 text-xs text-slate-500">Imported stock is added as opening stock; duplicate drugs are skipped without changing stock.</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('data.export', ['dataset' => $dataset, 'template' => 1]) }}"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Download CSV template</a>
            <a href="{{ route('data.export', $dataset) }}"
               class="rounded-lg border border-teal-700 px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50">Export records</a>
            <form method="POST" action="{{ route('data.import', $dataset) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required
                       class="max-w-52 rounded-lg border border-slate-300 text-xs file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2">
                <button class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">Import CSV</button>
            </form>
        </div>
    </div>

    @error('file')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror

    @if (session('csv_transfer_result.dataset') === $dataset)
        @php($result = session('csv_transfer_result'))
        <div class="mt-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            <p>{{ $result['added'] }} imported, {{ $result['skipped'] }} skipped, {{ count($result['errors']) }} row errors.</p>
            @if ($result['errors'])
                <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                    @foreach ($result['errors'] as $error)<li>{{ $error }}</li>@endforeach
                </ul>
                @if (count($result['errors']) === 50)<p class="mt-1 text-xs">Only the first 50 errors are shown.</p>@endif
            @endif
        </div>
    @endif
</section>
