<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial report</title>
    <style>
        @page { margin: 26px 30px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .letterhead { width: 100%; border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 12px; }
        .letterhead td { vertical-align: middle; }
        .logo { max-width: 105px; max-height: 58px; }
        .hospital-name { font-size: 18px; font-weight: bold; color: #0f3d3a; }
        .hospital-details { color: #475569; font-size: 8px; line-height: 1.55; }
        .report-title { padding: 8px 0 12px; }
        .eyebrow { color: #0f766e; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 17px; margin: 4px 0; }
        h2 { font-size: 11px; margin: 0 0 7px; }
        .muted { color: #64748b; }
        .chart-grid { width: 100%; border-spacing: 8px 0; margin: 0 -8px 14px; }
        .chart-grid > tbody > tr > td { width: 50%; vertical-align: top; }
        .chart { border: 1px solid #dbe4ea; border-radius: 5px; padding: 9px; }
        .chart-item { margin: 6px 0; }
        .chart-label { display: inline-block; width: 23%; color: #475569; }
        .chart-track { display: inline-block; vertical-align: middle; width: 57%; height: 8px; background: #e2e8f0; }
        .chart-bar { height: 8px; background: #0f766e; }
        .chart-value { display: inline-block; width: 18%; text-align: right; font-size: 8px; font-weight: bold; }
        .empty { color: #64748b; padding: 14px 0; text-align: center; }
        .cards { width: 100%; border-spacing: 7px 0; margin: 0 -7px 14px; }
        .cards td { background: #f1f5f9; border-radius: 5px; padding: 9px; width: 25%; }
        .card-label { color: #64748b; font-size: 8px; }
        .card-value { font-size: 13px; font-weight: bold; margin-top: 4px; }
        table.activity { border-collapse: collapse; width: 100%; margin-top: 5px; }
        .activity th { background: #e2e8f0; color: #334155; font-size: 7px; text-align: left; text-transform: uppercase; }
        .activity th, .activity td { border-bottom: 1px solid #e2e8f0; padding: 6px 5px; }
        .right { text-align: right; }
        .footer { color: #64748b; font-size: 8px; margin-top: 12px; }
    </style>
</head>
<body>
    <table class="letterhead">
        <tr>
            @if ($hospitalLogoPath)
                <td style="width: 120px"><img src="{{ $hospitalLogoPath }}" class="logo" alt="Hospital logo"></td>
            @endif
            <td>
                <div class="hospital-name">{{ $hospital->name }}</div>
                <div class="hospital-details">
                    @if ($hospital->address){{ $hospital->address }}<br>@endif
                    @if ($hospital->phone)Phone: {{ $hospital->phone }}@endif
                    @if ($hospital->phone && $hospital->email) &nbsp; | &nbsp; @endif
                    @if ($hospital->email)Email: {{ $hospital->email }}@endif
                    @if ($hospital->website)<br>{{ $hospital->website }}@endif
                </div>
            </td>
        </tr>
    </table>

    <div class="report-title">
        <div class="eyebrow">Accounting &amp; Finance</div>
        <h1>Financial report</h1>
        <div class="muted">Reporting period: {{ $rangeLabel }} · Generated {{ now()->format('d M Y, g:i A') }}</div>
    </div>

    <table class="chart-grid">
        <tr>
            <td>
                <div class="chart">
                    <h2>Collection trend</h2>
                    @php $maxTrend = max(1, collect($chart)->max('amount') ?? 0); @endphp
                    @forelse ($chart as $bucket)
                        <div class="chart-item">
                            <span class="chart-label">{{ $bucket['label'] }}</span>
                            <span class="chart-track"><span class="chart-bar" style="display:block;width:{{ max(0, min(100, ($bucket['amount'] / $maxTrend) * 100)) }}%"></span></span>
                            <span class="chart-value">₦{{ number_format($bucket['amount'], 0) }}</span>
                        </div>
                    @empty
                        <div class="empty">No collections in this period.</div>
                    @endforelse
                </div>
            </td>
            <td>
                <div class="chart">
                    <h2>Revenue by department</h2>
                    @php $maxDepartment = max(1, collect($departmentBreakdown)->max('amount') ?? 0); @endphp
                    @forelse ($departmentBreakdown as $row)
                        <div class="chart-item">
                            <span class="chart-label">{{ \Illuminate\Support\Str::limit($row['department'], 22) }}</span>
                            <span class="chart-track"><span class="chart-bar" style="display:block;width:{{ max(0, min(100, ($row['amount'] / $maxDepartment) * 100)) }}%"></span></span>
                            <span class="chart-value">₦{{ number_format($row['amount'], 0) }}</span>
                        </div>
                    @empty
                        <div class="empty">No department collections in this report.</div>
                    @endforelse
                </div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td><div class="card-label">Collections</div><div class="card-value">₦{{ number_format($collected, 2) }}</div></td>
            <td><div class="card-label">Transactions</div><div class="card-value">{{ number_format($transactions) }}</div></td>
            <td><div class="card-label">Average payment</div><div class="card-value">₦{{ number_format($average, 2) }}</div></td>
            <td><div class="card-label">Outstanding balance</div><div class="card-value">₦{{ number_format($outstanding, 2) }}</div></td>
        </tr>
    </table>

    <h2>Payment activity ({{ $payments->count() }} records)</h2>
    <table class="activity">
        <thead><tr><th>Date</th><th>Receipt / invoice</th><th>Patient</th><th>Department</th><th>Received by</th><th>Method</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->created_at->format('d M Y, g:i A') }}</td>
                    <td>{{ $payment->receipt_number }}<br><span class="muted">{{ $payment->invoice->invoice_number }}</span></td>
                    <td>{{ $payment->invoice->patient->full_name }}</td>
                    <td>{{ $payment->invoice->visit?->department?->name ?? 'Registration / general' }}</td>
                    <td>{{ $payment->receiver?->name ?? 'Patient portal' }}<br><span class="muted">{{ $payment->receiver?->role?->label() ?? 'Online payment' }}</span></td>
                    <td>{{ ucfirst($payment->method) }}</td>
                    <td class="right">₦{{ number_format($payment->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No payment activity found for this report.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">Report includes payments matching the selected date, department, and collection source filters.</div>
</body>
</html>
