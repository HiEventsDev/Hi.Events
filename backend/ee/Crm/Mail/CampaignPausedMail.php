<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHealthGuard;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/crm/campaign-paused.blade.php
 */
class CampaignPausedMail extends BaseMail
{
    use FlattensLines;

    public function __construct(
        private readonly int $campaignId,
        private readonly string $campaignName,
        private readonly string $accountName,
        private readonly int $accountId,
        private readonly string $reason,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Audience campaign paused: :name', ['name' => $this->campaignName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crm.campaign-paused',
            with: [
                'campaignId' => $this->campaignId,
                'campaignName' => $this->onOneLine($this->campaignName),
                'accountName' => $this->onOneLine($this->accountName),
                'accountId' => $this->accountId,
                'reason' => match ($this->reason) {
                    CampaignHealthGuard::REASON_COMPLAINTS => __('Too many recipients marked it as spam.'),
                    CampaignHealthGuard::REASON_BOUNCES => __('Too many addresses rejected it.'),
                    default => $this->reason,
                },
            ],
        );
    }
}
