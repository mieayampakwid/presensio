<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email fallback for a guardian absence alert (spec 05): sent when the
 * guardian has no phone number on file or their WhatsApp send failed
 * after the queue retries. All copy is prebuilt — a dumb envelope.
 */
class AbsenceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $guardianName,
        public readonly string $studentName,
        public readonly ?string $className,
        public readonly string $statusLabel,
        public readonly string $dateText,
        public readonly string $dateShort,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Absensi: {$this->studentName} {$this->statusLabel} {$this->dateShort}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.absence-alert',
        );
    }
}
