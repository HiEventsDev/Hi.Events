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
use HiEvents\Services\Infrastructure\Ai\UntrustedPromptText;
use Illuminate\Config\Repository;
use Illuminate\Support\Carbon;

class CampaignAiReviewService
{
    private const SUBJECT_MAX_LENGTH = 500;

    private const MESSAGE_MAX_LENGTH = 12000;

    private const NAME_MAX_LENGTH = 200;

    public function __construct(
        private readonly Repository $config,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignSmsPreviewService $smsPreviewService,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly UntrustedPromptText $promptText,
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
        $html = $campaign->isSms() ? '' : $this->mailFactory->make($campaign, null)->render();
        $unreadable = $this->whyItCannotBeReadInFull($campaign, $html);

        if ($unreadable !== null) {
            return new CampaignAiReviewResultDTO(approves: false, confidence: 0.0, reasons: [$unreadable]);
        }

        $response = (new CampaignReviewAgent)->prompt($this->buildPrompt($campaign, $html, $recipientsCount));

        $confidence = (float) ($response['confidence'] ?? 0.0);
        $threshold = (float) $this->config->get('app.crm_campaign_ai_review_confidence_threshold');

        return new CampaignAiReviewResultDTO(
            approves: (bool) ($response['approve'] ?? false) && $confidence >= $threshold,
            confidence: $confidence,
            reasons: array_values(array_map('strval', (array) ($response['reasons'] ?? []))),
        );
    }

    private function whyItCannotBeReadInFull(CrmCampaignDomainObject $campaign, string $html): ?string
    {
        if ($campaign->isSms()) {
            return null;
        }

        if (preg_match('/<img\b/i', (string) $campaign->getBody()) === 1) {
            return __('The message contains images, which the automatic check can\'t read.');
        }

        if ($this->promptText->holdsMoreThanIsRead($html, self::MESSAGE_MAX_LENGTH)) {
            return __('The message is too long for the automatic check to read all of it.');
        }

        return null;
    }

    /**
     * @throws CampaignOrganizerDeletedException
     */
    private function buildPrompt(CrmCampaignDomainObject $campaign, string $html, int $recipientsCount): string
    {
        $message = $campaign->isSms()
            ? $this->promptText->clip($this->smsPreviewService->preview($campaign, null)->variants[0]->text, self::MESSAGE_MAX_LENGTH)
            : $this->promptText->htmlToPlainText($html, self::MESSAGE_MAX_LENGTH);

        $account = $this->accountRepository->findById($campaign->getAccountId());
        $organizer = $this->organizerRepository->findFirstWhere(['id' => $campaign->getOrganizerId()]);
        $event = $campaign->getEventId() === null ? null : $this->eventRepository->findFirstWhere(['id' => $campaign->getEventId()]);
        $importOnly = $campaign->isSms() ? 0 : ($this->campaignRepository->countImportOnlyRecipients([$campaign->getId()])[$campaign->getId()] ?? 0);

        $facts = implode("\n", array_filter([
            '<channel>'.($campaign->isSms() ? 'text message' : 'email').'</channel>',
            '<held_for_review_because>'.$this->promptText->clip((string) $campaign->getStatusReason(), self::NAME_MAX_LENGTH).'</held_for_review_because>',
            '<organizer_name>'.$this->promptText->clip((string) $organizer?->getName(), self::NAME_MAX_LENGTH).'</organizer_name>',
            '<account_age_days>'.(int) Carbon::parse($account->getCreatedAt())->diffInDays(now()).'</account_age_days>',
            '<recipients>'.$recipientsCount.'</recipients>',
            '<recipients_opted_in_only_through_an_import>'.$importOnly.'</recipients_opted_in_only_through_an_import>',
            $event === null ? null : '<linked_event_title>'.$this->promptText->clip((string) $event->getTitle(), self::NAME_MAX_LENGTH).'</linked_event_title>',
            $campaign->isSms() ? null : '<subject>'.$this->promptText->clip((string) $campaign->getSubject(), self::SUBJECT_MAX_LENGTH).'</subject>',
            $campaign->isSms() || trim((string) $campaign->getPreheader()) === '' ? null : '<preheader>'.$this->promptText->clip((string) $campaign->getPreheader(), self::SUBJECT_MAX_LENGTH).'</preheader>',
        ]));
        $links = implode("\n", $this->promptText->extractLinks($campaign->isSms() ? $message : $html));

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
}
