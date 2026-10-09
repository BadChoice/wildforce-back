<?php

namespace App\Mail;

use App\Models\ClientInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ClientInvitation $invitation,
        public readonly string $token,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You\'ve been invited to Wildforce by :coach', ['coach' => $this->invitation->coach->name]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.client-invitations.invited',
            with: [
                'coach' => $this->invitation->coach,
                'invitationMessage' => $this->invitation->message,
                'invitationUrl' => route('client-invitations.show', ['token' => $this->token]),
            ],
        );
    }
}
