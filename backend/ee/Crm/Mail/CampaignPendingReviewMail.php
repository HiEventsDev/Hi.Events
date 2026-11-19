<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignSendGate;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/crm/campaign-pending-review.blade.php
 */
class CampaignPendingReviewMail extends BaseMail
{
    use FlattensLines;

    /**
     * @param  string[]  $automaticCheckFindings
     */
    public function __construct(
        private readonly int $campaignId,
        private readonly string $campaignName,
        private readonly string $campaignSubject,
        private readonly string $accountName,
        private readonly int $accountId,
        private readonly int $recipientsCount,
        private readonly string $reason,
        private readonly array $automaticCheckFindings = [],
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Audience campaign pending review: :name', ['name' => $this->campaignName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crm.campaign-pending-review',
            with: [
                'campaignId' => $this->campaignId,
                'campaignName' => $this->onOneLine($this->campaignName),
                'campaignSubject' => $this->onOneLine($this->campaignSubject),
                'accountName' => $this->onOneLine($this->accountName),
                'accountId' => $this->accountId,
                'recipientsCount' => $this->recipientsCount,
                'reason' => match ($this->reason) {
                    CampaignSendGate::REVIEW_REASON_UNTRUSTED_ACCOUNT => __('The account is not trusted to send without review yet.'),
                    CampaignSendGate::REVIEW_REASON_FIRST_CAMPAIGN => __('This is the first campaign the account has sent.'),
                    CampaignSendGate::REVIEW_REASON_RECENT_IMPORT => __('It reaches imported contacts that no approved campaign has reached yet.'),
                    default => $this->reason,
                },
                'automaticCheckFindings' => array_map($this->onOneLine(...), $this->automaticCheckFindings),
            ],
        );
    }
}
