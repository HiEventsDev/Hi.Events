<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;

class EventPublishedTrigger extends BaseAutomationTrigger
{
    public const PUBLISH_SETTLE_MINUTES = 30;

    public const SPAM_CHECK_WAIT_MINUTES = 120;

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSpamCheckService $spamCheckService,
        private readonly EventSpamCheckDispatchService $spamCheckDispatchService,
    ) {}

    public function dueRuns(CrmAutomationDomainObject $automation): array
    {
        $eventIds = $this->automationRepository->findNewlyLiveEventIds(
            $automation,
            now()->subMinutes(self::PUBLISH_SETTLE_MINUTES),
            (bool) $this->spamCheckService->isEnabled(),
        );
        $runs = [];

        foreach ($eventIds as $eventId) {
            $event = $this->eventRepository->findFirstWhere(['id' => $eventId]);

            if ($event === null) {
                continue;
            }

            $runs[] = new AutomationRunDTO(
                run_key: 'event:'.$eventId,
                label: $event->getTitle(),
                event: $event,
                attributes: [
                    ...$this->audienceAttributes($automation),
                    CrmCampaignDomainObjectAbstract::EVENT_ID => $eventId,
                ],
                keep_when_nobody_to_send_to: true,
            );
        }

        return $runs;
    }

    public function waitingOn(CrmAutomationDomainObject $automation): ?AutomationFailureDTO
    {
        if (! $this->spamCheckService->isEnabled()) {
            return null;
        }

        $event = $this->automationRepository->findEventAwaitingSpamCheck($automation, now()->subMinutes(self::SPAM_CHECK_WAIT_MINUTES));

        if ($event === null) {
            return null;
        }

        if (! $event->has_spam_check) {
            $this->spamCheckDispatchService->dispatchForEvent((int) $event->id);
        }

        return new AutomationFailureDTO(AutomationFailureDTO::EVENT_AWAITING_REVIEW, ['event_title' => (string) $event->title]);
    }
}
