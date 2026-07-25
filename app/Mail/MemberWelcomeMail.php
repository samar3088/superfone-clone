<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once, when an owner creates a team member.
 *
 * Carries a temporary password. It is passed as a plain constructor argument
 * and never persisted anywhere readable — the user record only holds the hash,
 * and `must_reset_password` forces the member to replace it on first use.
 */
class MemberWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public string $temporaryPassword,
        public string $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('company.name').' account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.member-welcome',
            with: [
                'firstName' => str($this->member->name)->trim()->explode(' ')->first(),
                'mobile' => $this->member->mobile,
                'password' => $this->temporaryPassword,
                'invitedBy' => $this->invitedBy,
                'url' => route('login'),
            ],
        );
    }
}
