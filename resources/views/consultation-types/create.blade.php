<x-app-layout>
    <x-slot name="header">Add Consultation Type</x-slot>

    <form method="POST" action="{{ route('consultation-types.store') }}" class="w-full rounded-xl border bg-white p-4 shadow-sm sm:p-6 lg:p-8">
        @csrf
        @include('consultation-types._form', ['consultationType' => null])

        <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('consultation-types.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</a>
            <button class="inline-flex min-h-11 min-w-40 items-center justify-center rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Add consultation type</button>
        </div>
    </form>
</x-app-layout>
