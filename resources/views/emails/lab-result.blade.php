<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6;">
    <h2 style="color: #115e59;">{{ $hospitalName }}</h2>
    <p>Hello {{ $labRequest->visit->patient->first_name }},</p>

    @if ($includeResult)
        <p>Your <strong>{{ $labRequest->test->name }}</strong> result is ready.</p>
        @if ($labRequest->result)
            <h3>Result</h3>
            <p style="white-space: pre-line;">{{ $labRequest->result }}</p>
        @endif
        @if ($labRequest->attachments->isNotEmpty())
            <p>Any attached reports or images are included with this email.</p>
        @endif
        <p style="font-size: 13px; color: #64748b;">Patient number: {{ $labRequest->visit->patient->hospital_number }}</p>
    @else
        <p>Your <strong>{{ $labRequest->test->name }}</strong> result has been uploaded and is available to your care team. Please contact the hospital or your doctor to discuss it.</p>
    @endif

    <p>Regards,<br>{{ $hospitalName }}</p>
</body>
</html>
