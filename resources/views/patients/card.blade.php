<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient card · {{ $patient->full_name }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 10mm; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #e2e8f0;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }
        .patient-card {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 85.6mm;
            height: 54mm;
            overflow: hidden;
            border: 0.25mm solid #cbd5e1;
            border-radius: 3mm;
            background: #fff;
            box-shadow: 0 12px 32px rgb(15 23 42 / 18%);
        }
        .accent { height: 2mm; flex: 0 0 2mm; background: #0f766e; }
        .card-content { display: flex; min-height: 0; flex: 1; flex-direction: column; padding: 3mm 4mm 2.5mm; }
        .hospital-header { display: flex; min-height: 8mm; align-items: center; gap: 2.5mm; border-bottom: 0.2mm solid #e2e8f0; padding-bottom: 1.5mm; }
        .logo { width: 8mm; height: 8mm; flex: 0 0 8mm; object-fit: contain; }
        .hospital-copy { min-width: 0; }
        .hospital-name { overflow: hidden; color: #134e4a; font-size: 3mm; font-weight: 700; line-height: 1.15; text-overflow: ellipsis; white-space: nowrap; }
        .hospital-details { display: -webkit-box; overflow: hidden; margin-top: 0.8mm; color: #64748b; font-size: 1.65mm; line-height: 1.25; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .patient-heading { margin-top: 2mm; color: #64748b; font-size: 1.65mm; font-weight: 700; letter-spacing: 0.3mm; text-transform: uppercase; }
        .patient-name { overflow: hidden; margin-top: 0.5mm; font-size: 3.4mm; font-weight: 700; line-height: 1.15; text-overflow: ellipsis; white-space: nowrap; }
        .hospital-number { margin-top: 0.7mm; color: #0f766e; font-family: 'Courier New', monospace; font-size: 2.8mm; font-weight: 700; letter-spacing: 0.15mm; }
        .patient-details { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2mm 4mm; margin-top: 1.8mm; }
        .detail-label { color: #64748b; font-size: 1.55mm; text-transform: uppercase; }
        .detail-value { overflow: hidden; margin-top: 0.3mm; font-size: 2mm; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
        .card-footer { display: flex; justify-content: space-between; gap: 2mm; margin-top: auto; border-top: 0.2mm solid #e2e8f0; padding-top: 1mm; color: #64748b; font-size: 1.55mm; }
        .no-print { margin-top: 20px; }
        .print-button { border: 0; border-radius: 8px; background: #0f766e; padding: 10px 20px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .back-link { margin-left: 12px; color: #475569; font-size: 14px; text-decoration: none; }
        @media print {
            html, body { width: auto; height: auto; min-height: 0; padding: 0; background: #fff; }
            body { display: block; }
            main { width: fit-content; }
            .patient-card { border-radius: 2mm; box-shadow: none; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <main>
        @php
            $hospitalDetails = array_filter([$hospital->address, $hospital->phone, $hospital->email, $hospital->website]);
            $addressParts = collect(preg_split('/[,;|]/', (string) $patient->address))
                ->map(fn ($part) => trim($part))
                ->filter()
                ->values();
            $patientArea = $addressParts->count() >= 2
                ? $addressParts->get($addressParts->count() - 2)
                : null;
        @endphp
        <article class="patient-card" aria-label="Patient identification card">
            <div class="accent"></div>
            <div class="card-content">
                <header class="hospital-header">
                    @if ($hospital->logo_url)
                        <img class="logo" src="{{ $hospital->logo_url }}" alt="{{ $hospital->name }} logo">
                    @else
                        <div class="logo" aria-hidden="true" style="display:grid;place-items:center;border-radius:50%;background:#ccfbf1;color:#0f766e;font-size:5mm;font-weight:700">+</div>
                    @endif
                    <div class="hospital-copy">
                        <div class="hospital-name">{{ $hospital->name }}</div>
                        @if ($hospitalDetails)
                            <div class="hospital-details">{{ implode(' · ', $hospitalDetails) }}</div>
                        @endif
                    </div>
                </header>

                <div class="patient-heading">Patient identification card</div>
                <div class="patient-name">{{ $patient->full_name }}</div>
                <div class="hospital-number">{{ $patient->hospital_number }}</div>

                <dl class="patient-details">
                    <div>
                        <dt class="detail-label">Age</dt>
                        <dd class="detail-value">{{ $patient->age !== null ? $patient->age.' years' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="detail-label">Sex</dt>
                        <dd class="detail-value">{{ ucfirst($patient->gender) }}</dd>
                    </div>
                    <div>
                        <dt class="detail-label">Area</dt>
                        <dd class="detail-value">{{ $patientArea ?: '—' }}</dd>
                    </div>
                    @if ($patient->is_nhis && $patient->nhis_number)
                        <div>
                            <dt class="detail-label">NHIS number</dt>
                            <dd class="detail-value">{{ $patient->nhis_number }}</dd>
                        </div>
                    @endif
                </dl>

                <footer class="card-footer">
                    <span>Present this card at every visit</span>
                    <span>Issued {{ $patient->registration_paid_at->format('d M Y') }}</span>
                </footer>
            </div>
        </article>

        <div class="no-print" style="text-align:center">
            <button class="print-button" type="button" onclick="window.print()">Print patient card</button>
            <a class="back-link" href="{{ route('patients.show', $patient) }}">Back to patient</a>
            <p style="margin-top:10px;color:#64748b;font-size:12px">Print at actual size (100%) and cut along the card edge.</p>
        </div>
    </main>
</body>
</html>
