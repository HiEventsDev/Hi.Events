<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/crm/campaign-rejected.blade.php
 */
class CampaignRejectedMail extends BaseMail
{
    use FlattensLines;

    public function __construct(
        private readonly string $campaignName,
        private readonly ?string $note,
        private readonly string $actionUrl,
        private readonly bool $isAutomationRun,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your campaign was not approved'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crm.campaign-rejected',
            with: [
                'campaignName' => $this->onOneLine($this->campaignName),
                'note' => $this->note === null ? null : $this->onOneLine($this->note),
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->isAutomationRun ? __('Open automations') : __('Open campaign'),
                'nextStep' => $this->isAutomationRun
                    ? __('This email was sent by one of your automations. Edit the automation\'s email and its next run will be reviewed again, or contact us if you think this is a mistake.')
                    : __('You can edit the campaign and send it again, or contact us if you think this is a mistake.'),
            ],
        );
    }
}
