<x-app-layout>
    <x-slot name="header">Register Patient</x-slot>

    <div class="mb-5 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.14em] text-teal-700">Patients · Registration</p>
            <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Create patient record</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">Enter the patient’s identity, contact, and clinical details. You can add the photograph now or later.</p>
        </div>
        <a href="{{ route('patients.index') }}" class="inline-flex min-h-10 items-center text-sm font-medium text-slate-600 hover:text-teal-700 sm:justify-end">← Back to patients</a>
    </div>

    <form method="POST" action="{{ route('patients.store') }}" enctype="multipart/form-data" class="w-full min-w-0 space-y-5 rounded-2xl border border-slate-200 bg-slate-50/60 p-3 shadow-sm sm:space-y-6 sm:p-5 lg:p-7">
        @csrf
        @include('patients._form', ['patient' => null])

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('patients.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-white">Cancel</a>
            <button class="inline-flex min-h-11 min-w-40 items-center justify-center rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Register patient</button>
        </div>
    </form>
</x-app-layout>
