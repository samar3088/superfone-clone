<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member a lead has landed on them.
 *
 * Only sent when an owner has switched lead emails on, and never for leads
 * pulled in by a backfill — see LeadService::intake().
 */
class NewLeadMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New lead: '.$this->lead->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-lead',
            with: ['rows' => $this->rows(), 'url' => route('leads.index')],
        );
    }

    /**
     * Only the fields that are actually filled — an email full of "—" is worse
     * than a short one. Custom form questions come through as-is.
     *
     * @return array<string, string>
     */
    private function rows(): array
    {
        $rows = array_filter([
            'Mobile' => $this->lead->mobile,
            'Email' => $this->lead->email,
            'Campaign' => $this->lead->campaign,
            'Stage' => $this->lead->stage?->name,
        ]);

        foreach ($this->lead->custom_data ?? [] as $key => $value) {
            if (filled($value)) {
                $rows[str($key)->replace('_', ' ')->title()->toString()] = (string) $value;
            }
        }

        return $rows;
    }
}
