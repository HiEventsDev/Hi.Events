<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\DTO\CampaignAiReviewResultDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsPreviewService;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Config\Repository;
use Illuminate\Support\Carbon;

class CampaignAiReviewService
{
    private const SUBJECT_MAX_LENGTH = 500;

    private const MESSAGE_MAX_LENGTH = 12000;

    private const NAME_MAX_LENGTH = 200;

    private const LINK_MAX_LENGTH = 300;

    private const MAX_LINKS = 40;

    public function __construct(
        private readonly Repository $config,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignSmsPreviewService $smsPreviewService,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->get('app.saas_mode_enabled')
            && $this->config->get('app.crm_campaign_ai_review_enabled')
            && $this->config->get('ai.providers.anthropic.key');
    }

    /**
     * @throws CampaignOrganizerDeletedException
     */
    public function review(CrmCampaignDomainObject $campaign, int $recipientsCount): CampaignAiReviewResultDTO
    {
        $response = (new CampaignReviewAgent)->prompt($this->buildPrompt($campaign, $recipientsCount));

        $confidence = (float) ($response['confidence'] ?? 0.0);
        $threshold = (float) $this->config->get('app.crm_campaign_ai_review_confidence_threshold');

        return new CampaignAiReviewResultDTO(
            approves: (bool) ($response['approve'] ?? false) && $confidence >= $threshold,
            confidence: $confidence,
            reasons: array_values(array_map('strval', (array) ($response['reasons'] ?? []))),
        );
    }

    /**
     * @throws CampaignOrganizerDeletedException
     */
    private function buildPrompt(CrmCampaignDomainObject $campaign, int $recipientsCount): string
    {
        $html = $campaign->isSms() ? '' : $this->mailFactory->make($campaign, null)->render();
        $message = $campaign->isSms()
            ? $this->clip($this->smsPreviewService->preview($campaign, null)->variants[0]->text, self::MESSAGE_MAX_LENGTH)
            : $this->toPlainText($html, self::MESSAGE_MAX_LENGTH);

        $account = $this->accountRepository->findById($campaign->getAccountId());
        $organizer = $this->organizerRepository->findFirstWhere(['id' => $campaign->getOrganizerId()]);
        $event = $campaign->getEventId() === null ? null : $this->eventRepository->findFirstWhere(['id' => $campaign->getEventId()]);
        $importOnly = $campaign->isSms() ? 0 : ($this->campaignRepository->countImportOnlyRecipients([$campaign->getId()])[$campaign->getId()] ?? 0);

        $facts = implode("\n", array_filter([
            '<channel>'.($campaign->isSms() ? 'text message' : 'email').'</channel>',
            '<held_for_review_because>'.$this->clip((string) $campaign->getStatusReason(), self::NAME_MAX_LENGTH).'</held_for_review_because>',
            '<organizer_name>'.$this->clip((string) $organizer?->getName(), self::NAME_MAX_LENGTH).'</organizer_name>',
            '<account_age_days>'.(int) Carbon::parse($account->getCreatedAt())->diffInDays(now()).'</account_age_days>',
            '<recipients>'.$recipientsCount.'</recipients>',
            '<recipients_opted_in_only_through_an_import>'.$importOnly.'</recipients_opted_in_only_through_an_import>',
            $event === null ? null : '<linked_event_title>'.$this->clip((string) $event->getTitle(), self::NAME_MAX_LENGTH).'</linked_event_title>',
            $campaign->isSms() ? null : '<subject>'.$this->clip((string) $campaign->getSubject(), self::SUBJECT_MAX_LENGTH).'</subject>',
            $campaign->isSms() || trim((string) $campaign->getPreheader()) === '' ? null : '<preheader>'.$this->clip((string) $campaign->getPreheader(), self::SUBJECT_MAX_LENGTH).'</preheader>',
        ]));
        $links = implode("\n", $this->extractLinks($campaign->isSms() ? $message : $html));

        return <<<PROMPT
        <campaign>
        {$facts}
        <message>
        {$message}
        </message>
        <links>
        {$links}
        </links>
        </campaign>
        PROMPT;
    }

    private function toPlainText(string $html, int $maxLength): string
    {
        $withoutHidden = preg_replace('/<(style|script|head)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $withInlineUrls = preg_replace_callback(
            '/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            fn (array $anchor): string => strip_tags($anchor[2]).' ('.$this->sanitiseUrl($anchor[1]).')',
            $withoutHidden,
        ) ?? $withoutHidden;

        return $this->clip(html_entity_decode(strip_tags($withInlineUrls), ENT_QUOTES | ENT_HTML5), $maxLength);
    }

    private function clip(string $text, int $maxLength): string
    {
        $withoutTags = str_replace(['<', '>'], ['‹', '›'], $text);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $withoutTags)), 0, $maxLength);
    }

    /**
     * @return string[]
     */
    private function extractLinks(string $content): array
    {
        preg_match_all('/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $content, $anchors);
        preg_match_all('/\bhttps?:\/\/[^\s"\'<>)]+/i', strip_tags($content), $bare);

        $links = array_map($this->sanitiseUrl(...), [...$anchors[1], ...$bare[0]]);

        return array_slice(array_values(array_unique(array_filter($links))), 0, self::MAX_LINKS);
    }

    private function sanitiseUrl(string $url): string
    {
        return mb_substr(trim(str_replace(['<', '>'], '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5))), 0, self::LINK_MAX_LENGTH);
    }
}
