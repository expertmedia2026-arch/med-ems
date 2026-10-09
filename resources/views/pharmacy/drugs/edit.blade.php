<x-app-layout>
    <x-slot name="header">Edit {{ $drug->name }}</x-slot>

    <form method="POST" action="{{ route('pharmacy.drugs.update', $drug) }}" class="max-w-3xl rounded-xl border bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('pharmacy.drugs._form')

        <p class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Current stock is <strong>{{ $drug->stock_quantity }}</strong>. Change it with <em>Restock</em> on the inventory page.
            Price changes apply to new prescriptions only; existing invoices keep their billed price.
        </p>

        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-800">Save changes</button>
            <a href="{{ route('pharmacy.inventory') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>