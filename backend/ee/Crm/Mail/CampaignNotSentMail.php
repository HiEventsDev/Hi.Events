<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/crm/campaign-not-sent.blade.php
 */
class CampaignNotSentMail extends BaseMail
{
    use FlattensLines;

    public function __construct(
        private readonly string $campaignName,
        private readonly string $reason,
        private readonly string $campaignUrl,
        private readonly bool $wasPartlySent,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crm.campaign-not-sent',
            with: [
                'heading' => $this->heading(),
                'summary' => $this->wasPartlySent
                    ? __('We stopped sending ":name", so the people who hadn\'t received it yet won\'t get it.', ['name' => $this->onOneLine($this->campaignName)])
                    : __('":name" didn\'t go out at its scheduled time, so nobody has received it. It\'s back in your drafts.', ['name' => $this->onOneLine($this->campaignName)]),
                'reason' => $this->onOneLine($this->reason),
                'campaignUrl' => $this->campaignUrl,
            ],
        );
    }

    private function heading(): string
    {
        return $this->wasPartlySent ? __('We stopped sending your campaign') : __('Your scheduled campaign was not sent');
    }
}
