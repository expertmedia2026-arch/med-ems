<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $payment->receipt_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@media print { .no-print { display: none !important; } body { background: #fff !important; } }</style>
</head>
<body class="bg-slate-100 py-8 font-sans text-slate-800">
    @php
        $invoice   = $payment->invoice;
        $isPatient = auth()->user()?->role === \App\Enums\Role::Patient;
        $backUrl   = $isPatient ? route('portal.invoices.show', $invoice) : route('billing.show', $invoice);
    @endphp

    <div class="mx-auto max-w-md rounded-xl bg-white p-8 shadow">

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

            <p class="mt-4 border-t pt-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Payment receipt</p>
        </div>

        <dl class="mt-5 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Receipt no.</dt><dd class="font-mono font-semibold">{{ $payment->receipt_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Invoice</dt><dd class="font-mono">{{ $invoice->invoice_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Patient</dt><dd>{{ $invoice->patient->full_name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Hospital no.</dt><dd class="font-mono">{{ $invoice->patient->hospital_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ $payment->created_at->format('d M Y, g:i A') }}</dd></div>
        </dl>

        {{-- Only what THIS payment covers --}}
        <table class="mt-6 w-full border-t text-sm">
            <tbody class="divide-y">
                @foreach ($payment->items as $item)
                    <tr>
                        <td class="py-2">
                            {{ $item->description }}
                            @if ($item->quantity > 1)<span class="text-slate-400">× {{ $item->quantity }}</span>@endif
                        </td>
                        <td class="py-2 text-right">₦{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2">
                <tr>
                    <td class="pt-3 font-semibold">Amount paid</td>
                    <td class="pt-3 text-right text-lg font-bold">₦{{ number_format($payment->amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-slate-500">Method</td>
                    <td class="text-right">{{ $payment->method === 'online' ? 'ONLINE' : strtoupper($payment->method) }}{{ $payment->reference ? ' · ' . $payment->reference : '' }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="mt-6 text-xs text-slate-500">
            {{ $payment->receiver ? 'Received by ' . $payment->receiver->name : 'Paid online' }}
        </p>

        <p class="mt-6 text-center text-xs text-slate-400">{{ $hospital->receipt_footer ?: 'Thank you. Get well soon.' }}</p>

        <div class="no-print mt-6 flex justify-center gap-3">
            <button onclick="window.print()" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-medium text-white hover:bg-teal-800">Print</button>
            <a href="{{ $backUrl }}" class="px-3 py-2 text-sm text-slate-500 hover:underline">Back to invoice</a>
        </div>
    </div>
</body>
</html>