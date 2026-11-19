<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Services\Domain\Mail\MailBrandingService;
use Illuminate\Contracts\Config\Repository;

class CampaignSenderName
{
    public function __construct(
        private readonly MailBrandingService $brandingService,
        private readonly Repository $config,
    ) {}

    public function for(CampaignMail $mail): string
    {
        $mailer = $this->config->get('mail.campaigns.mailer') ?? $this->config->get('mail.default');
        $mailerFrom = $this->config->get('mail.mailers.'.$mailer.'.from');
        $from = isset($mailerFrom['address']) ? $mailerFrom : $this->config->get('mail.from');

        return (string) ($this->brandingService->forMail($mail)->senderName ?? $from['name'] ?? $from['address']);
    }
}
