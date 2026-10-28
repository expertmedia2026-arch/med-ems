@props(['dataset', 'label'])

<section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-slate-900">CSV data tools <span class="font-normal text-slate-500">· {{ $label }}</span></h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">Download a template, export current records, or import a CSV file up to 10 MB. Existing matching records are skipped; imports do not overwrite records.</p>
            @if ($dataset === 'staff')
                <p class="mt-1 text-xs text-slate-500">Imported staff receive a 7-day account invitation by email. Passwords are never imported.</p>
            @elseif ($dataset === 'patients')
                <p class="mt-1 text-xs text-slate-500">Imported patients are treated as existing registrations, so no new registration fee is created.</p>
            @elseif ($dataset === 'pharmacy-catalog')
                <p class="mt-1 text-xs text-slate-500">Imported stock is added as opening stock; duplicate drugs are skipped without changing stock.</p>
            @endif
        </div>
        <div class="grid gap-2 sm:grid-cols-2 xl:flex xl:flex-wrap xl:items-center xl:justify-end">
            <a href="{{ route('data.export', ['dataset' => $dataset, 'template' => 1]) }}"
               class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 sm:w-auto">Download CSV template</a>
            <a href="{{ route('data.export', $dataset) }}"
               class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-teal-700 px-4 py-2.5 text-center text-sm font-semibold text-teal-700 transition hover:bg-teal-50 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 sm:w-auto">Export records</a>
            <form method="POST" action="{{ route('data.import', $dataset) }}" enctype="multipart/form-data" class="flex min-w-0 flex-col gap-2 sm:col-span-2 sm:flex-row xl:col-span-1">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required
                       class="min-h-11 w-full min-w-0 rounded-lg border border-slate-300 text-xs text-slate-600 file:mr-3 file:min-h-10 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium file:text-slate-700 hover:file:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 sm:flex-1 xl:w-56">
                <button class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 sm:w-auto">Import CSV</button>
            </form>
        </div>
    </div>

    @error('file')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror

    @if (session('csv_transfer_result.dataset') === $dataset)
        @php($result = session('csv_transfer_result'))
        <div class="mt-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            <p>{{ $result['added'] }} imported, {{ $result['skipped'] }} skipped, {{ count($result['errors']) }} row errors.</p>
            @if ($result['errors'])
                <ul class="mt-2 list-inside list-disc space-y-1 break-words text-xs">
                    @foreach ($result['errors'] as $error)<li>{{ $error }}</li>@endforeach
                </ul>
                @if (count($result['errors']) === 50)<p class="mt-1 text-xs">Only the first 50 errors are shown.</p>@endif
            @endif
        </div>
    @endif
</section>
