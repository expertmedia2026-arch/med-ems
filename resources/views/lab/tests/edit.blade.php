<x-app-layout>
    <x-slot name="header">Edit {{ $test->name }}</x-slot>

    <form method="POST" action="{{ route('lab.tests.update', $test) }}" class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('lab.tests._form')

        <p class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Price changes apply to <strong>new</strong> requests only. Invoices already generated keep the price they were billed at.
        </p>

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Save changes</button>
            <a href="{{ route('lab.tests.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>