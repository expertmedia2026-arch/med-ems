<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>General Hospital Report</title>
    <style>
        @page { margin: 22px; } body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9px; } h1, h2, p { margin: 0; } .header { border-bottom: 2px solid #0f766e; margin-bottom: 14px; padding-bottom: 10px; } .logo { float: left; height: 42px; margin-right: 10px; } .meta { color: #64748b; margin-top: 4px; } .grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 10px; } .card { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; } .label { color: #64748b; font-size: 8px; text-transform: uppercase; } .value { font-size: 16px; font-weight: bold; margin-top: 3px; } table.data { border-collapse: collapse; margin: 8px 0 12px; width: 100%; } .data th { background: #f1f5f9; color: #475569; text-align: left; } .data th, .data td { border: 1px solid #cbd5e1; padding: 5px; } .right { text-align: right !important; } .section-title { color: #0f766e; font-size: 12px; margin: 10px 0 4px; } .small { color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        @if ($hospitalLogoPath)<img src="{{ $hospitalLogoPath }}" class="logo" alt="">@endif
        <h1>{{ $hospital->name }}</h1>
        <h2>General Hospital Report</h2>
        <p class="meta">{{ $rangeLabel }} · {{ $selectedDepartment?->name ?? 'All departments' }} · Generated {{ now()->format('d M Y, g:i A') }}</p>
    </div>

    <table class="grid"><tr>
        <td class="card"><div class="label">New patients</div><div class="value">{{ number_format($patientSummary['registered']) }}</div><div class="small">NHIS {{ $patientSummary['nhis'] }} · Non-NHIS {{ $patientSummary['non_nhis'] }}</div></td>
        <td class="card"><div class="label">Encounters</div><div class="value">{{ number_format($clinicalSummary['visits']) }}</div><div class="small">{{ $clinicalSummary['consultations'] }} consultations</div></td>
        <td class="card"><div class="label">Admissions</div><div class="value">{{ number_format($admissionSummary['admitted']) }}</div><div class="small">{{ $admissionSummary['active_at_end'] }} active at period end</div></td>
        <td class="card"><div class="label">Collections</div><div class="value">N{{ number_format($billingSummary['collected'], 2) }}</div><div class="small">{{ $billingSummary['transactions'] }} transactions</div></td>
    </tr></table>

    <h2 class="section-title">Operational workflow</h2>
    <table class="data"><thead><tr><th>Area</th><th class="right">Total</th><th class="right">Completed / administered</th><th class="right">Pending / active</th></tr></thead><tbody>
        <tr><td>Appointments</td><td class="right">{{ $appointmentSummary['total'] }}</td><td class="right">{{ $appointmentSummary['statuses']['completed'] ?? 0 }}</td><td class="right">{{ $appointmentSummary['statuses']['scheduled'] ?? 0 }}</td></tr>
        <tr><td>Clinical visits</td><td class="right">{{ $clinicalSummary['visits'] }}</td><td class="right">{{ $clinicalSummary['completed'] }}</td><td class="right">{{ $clinicalSummary['triaged'] }} triaged</td></tr>
        <tr><td>Laboratory requests</td><td class="right">{{ $laboratorySummary['requested'] }}</td><td class="right">{{ $laboratorySummary['completed'] }}</td><td class="right">{{ $laboratorySummary['pending'] }}</td></tr>
        <tr><td>Prescriptions</td><td class="right">{{ $pharmacySummary['prescribed'] }}</td><td class="right">{{ $pharmacySummary['dispensed'] }}</td><td class="right">{{ $pharmacySummary['pending'] }}</td></tr>
        <tr><td>Medication administrations</td><td class="right">{{ $medicationSummary['total'] }}</td><td class="right">{{ $medicationSummary['administered'] }}</td><td class="right">—</td></tr>
        <tr><td>Admissions</td><td class="right">{{ $admissionSummary['admitted'] }}</td><td class="right">{{ $admissionSummary['discharged'] }} discharged</td><td class="right">{{ $admissionSummary['active_at_end'] }} active</td></tr>
        <tr><td>Staff</td><td class="right">{{ $staffSummary['active'] }} active</td><td class="right">{{ $staffSummary['scheduled_duties'] }} rostered duties</td><td class="right">—</td></tr>
    </tbody></table>

    <h2 class="section-title">Billing</h2>
    <table class="data"><thead><tr><th>Invoices</th><th class="right">Billed</th><th class="right">Paid on invoices</th><th class="right">Outstanding</th><th class="right">Collections in period</th></tr></thead><tbody><tr><td>{{ $billingSummary['invoices'] }}</td><td class="right">N{{ number_format($billingSummary['billed'], 2) }}</td><td class="right">N{{ number_format($billingSummary['invoice_paid'], 2) }}</td><td class="right">N{{ number_format($billingSummary['outstanding'], 2) }}</td><td class="right">N{{ number_format($billingSummary['collected'], 2) }}</td></tr></tbody></table>

    <h2 class="section-title">Department performance</h2>
    <table class="data"><thead><tr><th>Department</th><th class="right">Visits</th><th class="right">Admissions</th><th class="right">Active staff</th><th class="right">Collections</th></tr></thead><tbody>
        @forelse ($departmentPerformance as $row)<tr><td>{{ $row['department'] }}</td><td class="right">{{ $row['visits'] }}</td><td class="right">{{ $row['admissions'] }}</td><td class="right">{{ $row['staff'] }}</td><td class="right">N{{ number_format($row['revenue'], 2) }}</td></tr>@empty<tr><td colspan="5">No department activity in this period.</td></tr>@endforelse
    </tbody></table>

    <h2 class="section-title">Top diagnoses</h2>
    <table class="data"><thead><tr><th>Diagnosis</th><th class="right">Cases</th></tr></thead><tbody>@forelse ($topDiagnoses as $diagnosis)<tr><td>{{ $diagnosis['diagnosis'] }}</td><td class="right">{{ $diagnosis['total'] }}</td></tr>@empty<tr><td colspan="2">No diagnoses recorded.</td></tr>@endforelse</tbody></table>
</body>
</html>
