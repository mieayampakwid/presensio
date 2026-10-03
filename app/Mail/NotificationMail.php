<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $mailBody,
        public readonly ?string $mailUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        $body = $this->mailBody;
        if ($this->mailUrl !== null) {
            $body .= "\n\nBuka di aplikasi: ".$this->mailUrl;
        }

        return new Content(
            htmlString: nl2br(e($body)),
        );
    }
}
