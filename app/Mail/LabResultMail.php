<?php

namespace App\Mail;

use App\Models\LabRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LabResultMail extends Mailable
{
    use Queueable;

    public function __construct(
        public LabRequest $labRequest,
        public string $hospitalName,
        public bool $includeResult,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->includeResult
            ? "Your {$this->labRequest->test->name} result from {$this->hospitalName}"
            : "Your {$this->labRequest->test->name} result is ready";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lab-result');
    }

    public function attachments(): array
    {
        if (! $this->includeResult) {
            return [];
        }

        return $this->labRequest->attachments->map(fn ($attachment) => Attachment::fromStorageDisk('local', $attachment->path)
            ->as($attachment->original_name)
            ->withMime($attachment->mime))
            ->all();
    }
}
