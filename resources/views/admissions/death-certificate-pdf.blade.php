<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Death certificate · {{ $case->death_certificate_number }}</title>
    <style>
        @page { margin: 22mm 20mm; }
        body { font: 13px/1.55 Arial, sans-serif; color: #172033; }
        .header { text-align: center; border-bottom: 2px solid #0f766e; padding-bottom: 16px; }
        .hospital { font-size: 19px; font-weight: 700; color: #0f3d3a; }
        .muted { color: #526174; }
        h1 { margin: 22px 0 4px; text-align: center; font-size: 22px; letter-spacing: 1px; }
        .number { text-align: center; font-family: monospace; color: #0f766e; font-size: 14px; }
        .intro { margin: 18px 0; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 14px 0; }
        th, td { border: 1px solid #cbd5e1; padding: 9px 10px; text-align: left; vertical-align: top; }
        th { width: 29%; background: #f1f5f9; }
        .section-title { margin: 20px 0 6px; color: #0f3d3a; font-size: 14px; }
        .text-block { min-height: 44px; border: 1px solid #cbd5e1; padding: 10px; white-space: pre-wrap; }
        .certifier { margin-top: 28px; border: 1px solid #cbd5e1; padding: 14px; }
        .signature-line { width: 55%; margin-top: 46px; padding-top: 5px; border-top: 1px solid #526174; }
        .footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid #cbd5e1; font-size: 10px; color: #526174; }
    </style>
</head>
<body>
    <header class="header">
        <div class="hospital">{{ $hospital->name }}</div>
        @if ($hospital->address)<div>{{ $hospital->address }}</div>@endif
        @if ($hospital->phone || $hospital->email)<div class="muted">{{ $hospital->phone }}@if ($hospital->phone && $hospital->email) · @endif{{ $hospital->email }}</div>@endif
    </header>

    <h1>HOSPITAL DEATH CERTIFICATE</h1>
    <div class="number">Certificate no. {{ $case->death_certificate_number }}</div>
    <p class="intro">This certifies the hospital record of the death of the person identified below.</p>

    <table>
        <tr><th>Full name</th><td>{{ $admission->patient->full_name }}</td></tr>
        <tr><th>Hospital number</th><td>{{ $admission->patient->hospital_number }}</td></tr>
        <tr><th>Date of birth / age</th><td>{{ $admission->patient->date_of_birth?->format('d M Y') ?? 'Not recorded' }} · {{ $admission->patient->age !== null ? $admission->patient->age.' years' : 'Age not recorded' }}</td></tr>
        <tr><th>Sex</th><td>{{ ucfirst($admission->patient->gender ?? 'Not recorded') }}</td></tr>
        <tr><th>Admission number</th><td>{{ $admission->admission_number }}</td></tr>
        <tr><th>Ward</th><td>{{ $admission->department->name }}</td></tr>
        <tr><th>Date and time of death</th><td>{{ $case->deceased_at?->format('d M Y, g:i A') ?? 'Not recorded' }}</td></tr>
    </table>

    <h2 class="section-title">Certified cause of death</h2>
    <div class="text-block">{{ $case->death_certificate_cause }}</div>

    @if ($case->death_certificate_other_conditions)
        <h2 class="section-title">Other significant conditions</h2>
        <div class="text-block">{{ $case->death_certificate_other_conditions }}</div>
    @endif

    <section class="certifier">
        <strong>Certifying doctor</strong>
        <table>
            <tr><th>Name</th><td>{{ $case->death_certificate_issuer_name }}</td></tr>
            <tr><th>Professional registration no.</th><td>{{ $case->death_certificate_registration_number }}</td></tr>
            <tr><th>Certificate issued</th><td>{{ $case->death_certificate_issued_at?->format('d M Y, g:i A') }}</td></tr>
        </table>
        <p>I certify that the particulars above are recorded from the hospital clinical record for this inpatient death.</p>
        <div class="signature-line">Certifying doctor's signature</div>
    </section>

    <footer class="footer">
        Issued by {{ $case->death_certificate_issued_by?->name ?? $case->death_certificate_issuer_name }}. This is a hospital-issued clinical certificate; complete any separate civil registration process required by the relevant authority.
    </footer>
</body>
</html>
