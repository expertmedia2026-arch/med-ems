<?php

namespace App\Mail;

use App\Models\Patient;
use App\Models\PatientInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PatientInvitationMail extends Mailable
{
    public function __construct(
        public PatientInvitation $invitation,
        public string $url,
        public string $hospitalName,
        public Patient $patient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Activate your {$this->hospitalName} patient portal");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.patient-invitation');
    }
}