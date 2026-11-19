<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenRenderer;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignUnsubscribeTokenService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;

class CampaignSmsComposer
{
    public const MAX_BODY_LENGTH = 1000;

    public function __construct(
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignTokenRenderer $tokenRenderer,
        private readonly CampaignUnsubscribeTokenService $unsubscribeTokenService,
    ) {}

    /**
     * @throws CampaignOrganizerDeletedException
     */
    public function compose(CrmCampaignDomainObject $campaign, ?int $recipientId, ?CampaignRecipientDTO $recipient, ?SmsCountry $country): string
    {
        ['organizer_name' => $organizerName, 'tokens' => $tokens] = $this->mailFactory->textContext($campaign, $recipientId, $recipient);

        return $this->wrap($organizerName, $this->tokenRenderer->renderText((string) $campaign->getBody(), $tokens), $country, $recipientId);
    }

    private function wrap(string $organizerName, string $text, ?SmsCountry $country, ?int $recipientId): string
    {
        $footer = $country?->optsOutByReply()
            ? __('Reply STOP to opt out.')
            : __('Opt out: :url', ['url' => $recipientId === null ? $this->unsubscribeTokenService->sampleSmsUrl() : $this->unsubscribeTokenService->smsUrl($recipientId)]);

        return trim($organizerName).': '.trim($text)."\n".$footer;
    }
}
