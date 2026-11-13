<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** The daily email to a business: what is due, what is coming, what is late. */
class DuesDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $today,
        public Collection $coming,
        public Collection $late,
        public Collection $entries,
    ) {
    }

    public function envelope(): Envelope
    {
        $parts = [];
        if ($this->coming->isNotEmpty()) {
            $parts[] = $this->coming->count().' coming up';
        }
        if ($this->late->isNotEmpty()) {
            $parts[] = $this->late->count().' late';
        }
        if ($this->entries->isNotEmpty()) {
            $parts[] = $this->entries->count().' with people';
        }

        return new Envelope(subject: config('app.name').': '.implode(', ', $parts));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.digest');
    }
}
