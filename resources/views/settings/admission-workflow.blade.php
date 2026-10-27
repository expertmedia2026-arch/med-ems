<x-app-layout>
    <x-slot name="header">Admission workflow</x-slot>

    <div class="max-w-4xl space-y-6">
        @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="rounded-xl border bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Choose admission chart sections</h2>
                <p class="mt-1 text-sm text-slate-600">Admission details, vitals, and care notes stay available. Choose which specialized charts your hospital uses. This creates a new workflow version and keeps existing records.</p>
            </div>

            <form method="POST" action="{{ route('settings.admission-workflow.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')
                <fieldset class="space-y-3">
                    <legend class="mb-3 text-sm font-semibold text-slate-900">Optional sections · current version {{ $version ?: 'default' }}</legend>
                    @foreach ($sections as $key => $label)
                        <label class="flex items-start gap-3 rounded-lg border p-4 text-sm text-slate-700">
                            <input type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key, old('sections', $enabledSections), true)) class="mt-0.5 rounded border-slate-300 text-teal-700">
                            <span><strong>{{ $label }}</strong></span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="flex justify-end"><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Save workflow</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
