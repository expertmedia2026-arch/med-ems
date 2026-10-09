<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StaffInvitationMail extends Mailable
{
    public function __construct(
        public StaffInvitation $invitation,
        public string $url,
        public string $hospitalName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're invited to join {$this->hospitalName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-invitation');
    }
}