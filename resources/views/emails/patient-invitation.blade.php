<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6;">
    <h2 style="color: #115e59;">{{ $hospitalName }}</h2>

    <p>Hello {{ $patient->first_name }},</p>
    <p>
        You have been registered at <strong>{{ $hospitalName }}</strong>
        (hospital number <strong>{{ $patient->hospital_number }}</strong>).
        Confirm your email and choose a password to use your patient portal:
    </p>

    <p>
        <a href="{{ $url }}"
           style="display:inline-block;background:#0f766e;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">
            Verify email and set password
        </a>
    </p>

    <p>
        Your portal login becomes active once your registration fee has been paid at the cashier.
    </p>

    <p style="font-size: 13px; color: #64748b;">
        This link expires on {{ $invitation->expires_at->format('d M Y, g:i A') }} and can only be used once.
        If the button does not work, copy this address into your browser:<br>
        {{ $url }}
    </p>
</body>
</html>