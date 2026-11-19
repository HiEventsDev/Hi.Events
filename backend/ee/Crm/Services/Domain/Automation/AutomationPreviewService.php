<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFirstSendDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationPreviewDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\PreviewAutomationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\BeforeEventTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\DailyTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHtmlSanitizer;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenRenderer;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Support\Carbon;

class AutomationPreviewService
{
    private const UNSAVED_CAMPAIGN_ID = 0;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly AutomationDefaults $defaults,
        private readonly CampaignTokenRenderer $tokenRenderer,
        private readonly CampaignAudienceService $audienceService,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignHtmlSanitizer $sanitizer,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     * @throws CampaignOrganizerDeletedException
     */
    public function preview(PreviewAutomationDTO $dto): AutomationPreviewDTO
    {
        $automation = $this->savedAutomation($dto);
        $occurrence = $this->sampleOccurrence($dto);
        $campaign = $this->campaign($dto, $occurrence);
        $mail = $this->mailFactory->makeAutomationPreview($campaign, $dto->viewer_name);

        return new AutomationPreviewDTO(
            mailable_count: $this->reach($dto, $this->asRunOf($automation, $campaign), $occurrence),
            first_send: $this->firstSend($dto, $automation, $occurrence, $campaign->getEventTitle()),
            subject: $mail->getSubjectLine(),
            html: $mail->render(),
        );
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     * @throws CampaignOrganizerDeletedException
     */
    public function mail(PreviewAutomationDTO $dto): CampaignMail
    {
        return $this->mailFactory->makeAutomationPreview($this->campaign($dto, $this->sampleOccurrence($dto)), $dto->viewer_name);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function savedAutomation(PreviewAutomationDTO $dto): ?CrmAutomationDomainObject
    {
        if ($dto->automation_id === null) {
            return null;
        }

        return $this->automationRepository->findFirstWhere([
            CrmAutomationDomainObjectAbstract::ID => $dto->automation_id,
            CrmAutomationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]) ?? throw new ResourceNotFoundException(__('Automation not found'));
    }

    private function asRunOf(?CrmAutomationDomainObject $automation, CrmCampaignDomainObject $campaign): CrmCampaignDomainObject
    {
        return $automation === null
            ? $campaign
            : (clone $campaign)->setId(self::UNSAVED_CAMPAIGN_ID)->setAutomationId($automation->getId());
    }

    private function sampleOccurrence(PreviewAutomationDTO $dto): ?object
    {
        return match ($dto->trigger) {
            CrmAutomationTrigger::AFTER_EVENT => $this->automationRepository->findNextEndingOccurrence($dto->organizer_id, now()),
            CrmAutomationTrigger::BEFORE_EVENT => $this->nextReminderOccurrence($dto),
            default => null,
        };
    }

    private function nextReminderOccurrence(PreviewAutomationDTO $dto): ?object
    {
        foreach ($this->automationRepository->findUpcomingOccurrences($dto->organizer_id, now()) as $occurrence) {
            if (BeforeEventTrigger::reminderSendTime($occurrence->start_date, $occurrence->timezone, $dto->config->days_before)->gt(now())) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    private function reach(PreviewAutomationDTO $dto, CrmCampaignDomainObject $campaign, ?object $occurrence): int
    {
        return match ($dto->trigger) {
            CrmAutomationTrigger::ABANDONED_CHECKOUT, CrmAutomationTrigger::SEGMENT_ENTERED, CrmAutomationTrigger::WELCOME => 0,
            CrmAutomationTrigger::AFTER_EVENT, CrmAutomationTrigger::BEFORE_EVENT => $occurrence === null ? 0 : $this->audienceService->count($campaign)->mailable_count,
            default => $this->audienceService->count($campaign)->mailable_count,
        };
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InvalidSegmentDefinitionException
     */
    private function campaign(PreviewAutomationDTO $dto, ?object $occurrence): CrmCampaignDomainObject
    {
        $audience = $this->defaults->audience($dto->trigger, $dto->segment_id, $dto->preset);
        $this->segmentResolver->resolveDefinition($dto->organizer_id, $audience, $dto->can_view_financials);

        $event = $this->sampleEvent($dto, $occurrence);
        $values = [
            'organizer_name' => $this->organizerRepository->findById($dto->organizer_id)->getName(),
            'event_title' => $dto->trigger->isEventScoped() ? ($event?->getTitle() ?? __('Your next event')) : null,
            ...($event === null ? ['event_date' => null] : []),
        ];

        $campaign = (new CrmCampaignDomainObject)
            ->setAccountId($dto->account_id)
            ->setOrganizerId($dto->organizer_id)
            ->setSubject($this->tokenRenderer->renderSubject($dto->subject, $values))
            ->setPreheader($dto->preheader === null ? null : $this->tokenRenderer->renderSubject($dto->preheader, $values))
            ->setBody((string) $this->sanitizer->sanitize($this->tokenRenderer->renderBody($dto->body, $values)))
            ->setSegmentId($audience->segment_id)
            ->setPresetSegment($audience->preset)
            ->setEventTitle($event?->getTitle())
            ->setAudienceIsExplicit(false)
            ->setExcludesOccurrenceHolders(false);

        if ($dto->trigger === CrmAutomationTrigger::AFTER_EVENT && $occurrence !== null) {
            return $campaign
                ->setEventOccurrenceId((int) $occurrence->id)
                ->setSegmentDefinition($this->defaults->afterEventDefinition((int) $occurrence->event_id));
        }

        if ($dto->trigger === CrmAutomationTrigger::BEFORE_EVENT && $occurrence !== null) {
            return $campaign
                ->setEventId((int) $occurrence->event_id)
                ->setEventOccurrenceId((int) $occurrence->id)
                ->setExcludesOccurrenceHolders($dto->config->targetsNonBuyers());
        }

        return $campaign->setEventId(in_array($dto->trigger, [CrmAutomationTrigger::EVENT_PUBLISHED, CrmAutomationTrigger::ABANDONED_CHECKOUT], true) ? $event?->getId() : null);
    }

    private function sampleEvent(PreviewAutomationDTO $dto, ?object $occurrence): ?EventDomainObject
    {
        $eventId = match ($dto->trigger) {
            CrmAutomationTrigger::AFTER_EVENT, CrmAutomationTrigger::BEFORE_EVENT => $occurrence === null ? null : (int) $occurrence->event_id,
            CrmAutomationTrigger::EVENT_PUBLISHED, CrmAutomationTrigger::ABANDONED_CHECKOUT => $this->automationRepository->findLiveUpcomingEventIds($dto->organizer_id, now())[0] ?? null,
            default => null,
        };

        return $eventId === null ? null : $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $eventId,
            EventDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
        ]);
    }

    private function firstSend(PreviewAutomationDTO $dto, ?CrmAutomationDomainObject $automation, ?object $occurrence, ?string $eventTitle): AutomationFirstSendDTO
    {
        return match ($dto->trigger) {
            CrmAutomationTrigger::EVENT_PUBLISHED => new AutomationFirstSendDTO(AutomationFirstSendDTO::NEXT_PUBLISH, null, null, null),
            CrmAutomationTrigger::AFTER_EVENT => new AutomationFirstSendDTO(
                kind: AutomationFirstSendDTO::AFTER_DATE,
                at: $occurrence === null ? null : Carbon::parse($occurrence->end_date ?? $occurrence->start_date, 'UTC')->addDays($dto->config->delay_days)->toISOString(),
                event_title: $eventTitle,
                event_date: $occurrence === null ? null : Carbon::parse($occurrence->start_date, 'UTC')->toISOString(),
            ),
            CrmAutomationTrigger::BEFORE_EVENT => new AutomationFirstSendDTO(
                kind: AutomationFirstSendDTO::BEFORE_DATE,
                at: $occurrence === null ? null : BeforeEventTrigger::reminderSendTime($occurrence->start_date, $occurrence->timezone, $dto->config->days_before)->toISOString(),
                event_title: $eventTitle,
                event_date: $occurrence === null ? null : Carbon::parse($occurrence->start_date, 'UTC')->toISOString(),
            ),
            CrmAutomationTrigger::ABANDONED_CHECKOUT => new AutomationFirstSendDTO(AutomationFirstSendDTO::AFTER_ABANDON, null, null, null),
            CrmAutomationTrigger::SEGMENT_ENTERED => new AutomationFirstSendDTO(AutomationFirstSendDTO::ON_ENTRY, $this->nextQuarterHour()->toISOString(), null, null),
            CrmAutomationTrigger::WIN_BACK, CrmAutomationTrigger::WELCOME => new AutomationFirstSendDTO(AutomationFirstSendDTO::DAILY, $this->nextDailyRun($dto->organizer_id, $automation)->toISOString(), null, null),
        };
    }

    private function nextDailyRun(int $organizerId, ?CrmAutomationDomainObject $automation): Carbon
    {
        $timezone = $this->organizerRepository->findById($organizerId)->getTimezone();
        $sendTimeToday = DailyTrigger::sendTimeOn(now(), $timezone);

        if (DailyTrigger::hasRunOn(now(), $timezone, $automation?->getLastRunAt())) {
            return DailyTrigger::sendTimeOn(now()->setTimezone($timezone)->addDay(), $timezone);
        }

        return $sendTimeToday->gt(now()) ? $sendTimeToday : $this->nextQuarterHour();
    }

    private function nextQuarterHour(): Carbon
    {
        $minutes = (intdiv(now('UTC')->minute, AutomationRunner::RUN_INTERVAL_MINUTES) + 1) * AutomationRunner::RUN_INTERVAL_MINUTES;

        return now('UTC')->startOfHour()->addMinutes($minutes);
    }
}
