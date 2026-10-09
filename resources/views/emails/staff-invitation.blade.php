<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6;">
    <h2 style="color: #115e59;">{{ $hospitalName }}</h2>

    <p>Hello,</p>
    <p>
        You have been invited to join <strong>{{ $hospitalName }}</strong> as
        <strong>{{ $invitation->role->label() }}</strong>.
    </p>
    <p>Click the button below to set your name and password and activate your account:</p>

    <p>
        <a href="{{ $url }}"
           style="display:inline-block;background:#0f766e;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">
            Accept invitation
        </a>
    </p>

    <p style="font-size: 13px; color: #64748b;">
        This link expires on {{ $invitation->expires_at->format('d M Y, g:i A') }} and can only be used once.
        If the button does not work, copy this address into your browser:<br>
        {{ $url }}
    </p>
</body>
</html>