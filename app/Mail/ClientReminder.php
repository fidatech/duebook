<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The email to a client: "your hosting is due in 7 days". It carries the name of the business, and replies go to the business. */
class ClientReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public Contract $contract, public int $daysBefore)
    {
    }

    public function envelope(): Envelope
    {
        $when = $this->daysBefore === 0 ? 'is due today' : 'is due on '.fmt_date($this->contract->next_due_date);
        $reply = $this->tenant->email ?: $this->tenant->notify_email;

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->tenant->name),
            replyTo: $reply ? [new Address($reply, $this->tenant->name)] : [],
            subject: $this->contract->title.' '.$when,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client');
    }
}
