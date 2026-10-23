<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DAMA discharge · {{ $admission->admission_number }}</title>
    <style>
        body{font:15px/1.55 Arial,sans-serif;color:#172033;margin:32px auto;max-width:850px;padding:0 24px}h1{text-align:center;font-size:22px;margin:24px 0 4px}h2{font-size:16px;margin:24px 0 6px}.muted{color:#526174}.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;border-block:1px solid #ccd4df;padding:14px 0}.block{white-space:pre-wrap;border:1px solid #ccd4df;padding:12px;min-height:54px}.witnesses{display:flex;gap:24px;flex-wrap:wrap}.witness{width:240px}.witness img{max-width:220px;max-height:170px;object-fit:contain;border:1px solid #ccd4df;padding:4px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:48px 32px;margin-top:48px}.line{border-top:1px solid #526174;padding-top:6px;margin-top:45px}.actions{text-align:right;margin-bottom:20px}.actions button{padding:9px 16px}@media print{body{margin:0 auto;padding:0 12px}.actions{display:none}}
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
    <header style="text-align:center">
        <h2 style="font-size:20px">{{ \App\Models\HospitalSetting::current()->name }}</h2>
        <div class="muted">{{ \App\Models\HospitalSetting::current()->address }}</div>
        <h1>DISCHARGE AGAINST MEDICAL ADVICE</h1>
        <div class="muted">Admission record {{ $admission->admission_number }}</div>
    </header>
    <section class="grid" style="margin-top:24px">
        <div><strong>Patient:</strong> {{ $admission->patient->full_name }}</div>
        <div><strong>Hospital number:</strong> {{ $admission->patient->hospital_number }}</div>
        <div><strong>Ward:</strong> {{ $admission->department->name }}</div>
        <div><strong>Attending doctor:</strong> {{ $admission->attendingDoctor->name }}</div>
        <div><strong>Admission date:</strong> {{ $admission->admitted_at->format('d M Y, g:i A') }}</div>
        <div><strong>DAMA recorded:</strong> {{ $record->completed_at->format('d M Y, g:i A') }}</div>
        <div><strong>Patient / representative:</strong> {{ $record->patient_or_representative }}</div>
        <div><strong>Relationship:</strong> {{ $record->relationship ?: '—' }}</div>
    </section>
    <h2>Reason for leaving</h2><div class="block">{{ $record->reason }}</div>
    <h2>Risks, alternatives, and return advice documented as discussed</h2><div class="block">{{ $record->risks_explained }}</div>
    <h2>Recommended next steps and urgent return instructions</h2><div class="block">{{ $record->follow_up_advice }}</div>
    @if($record->additional_notes)<h2>Additional notes</h2><div class="block">{{ $record->additional_notes }}</div>@endif
    <h2>Witnesses</h2>
    <div class="witnesses">@foreach($record->witnesses as $witness)<div class="witness"><strong>{{ $witness->name }}</strong><br><img src="{{ route('admissions.dama.witness', [$admission, $witness]) }}" alt="Witness image"><div class="line">Witness signature</div></div>@endforeach</div>
    <div class="signatures">
        <div class="line">Patient / representative signature or mark</div>
        <div class="line">Clinician: {{ $record->clinician->name }}</div>
        <div class="line">Date and time</div>
        <div class="line">Contact / follow-up details</div>
    </div>
    <p class="muted" style="margin-top:28px;font-size:12px">This record documents the details entered by hospital staff. Attach or complete any additional pages required by the hospital’s approved DAMA form.</p>
</body>
</html>
