<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation;

use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmAutomationRunOutcome;
use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Exceptions\AutomationNotRunnableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\NoMailableRecipientsException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationRunDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\AbandonedCheckoutTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\AfterEventTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\AutomationTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\BeforeEventTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\DailyTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\EventPublishedTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\SegmentEnteredTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignHtmlSanitizer;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignLaunchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenRenderer;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class AutomationRunner
{
    public const RUN_INTERVAL_MINUTES = 15;

    public const REASON_AUTOMATION_SKIPPED = 'AUTOMATION_SKIPPED';

    private const FAILURES_ONLY_A_SENT_EMAIL_SETTLES = [
        AutomationFailureDTO::REVIEW_EXPIRED,
        CampaignNotSendableException::REASON_TOO_MANY_RECIPIENTS,
        CampaignNotSendableException::REASON_LINKS_NOT_ALLOWED,
    ];

    private const TRIGGERS_BATCHED_WHILE_IN_REVIEW = [CrmAutomationTrigger::ABANDONED_CHECKOUT, CrmAutomationTrigger::SEGMENT_ENTERED];

    public function __construct(
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly CampaignLaunchService $launchService,
        private readonly CampaignTokenRenderer $tokenRenderer,
        private readonly CampaignHtmlSanitizer $sanitizer,
        private readonly DatabaseManager $databaseManager,
        private readonly EventPublishedTrigger $eventPublished,
        private readonly AfterEventTrigger $afterEvent,
        private readonly BeforeEventTrigger $beforeEvent,
        private readonly AbandonedCheckoutTrigger $abandonedCheckout,
        private readonly SegmentEnteredTrigger $segmentEntered,
        private readonly DailyTrigger $daily,
    ) {}

    /**
     * @throws Throwable
     */
    public function run(CrmAutomationDomainObject $automation): void
    {
        $kind = CrmAutomationTrigger::from($automation->getTrigger());
        $trigger = $this->triggerFor($kind);
        $this->discardOrphanedDrafts($automation);

        if (in_array($kind, self::TRIGGERS_BATCHED_WHILE_IN_REVIEW, true) && $this->hasRunInReview($automation)) {
            return;
        }

        try {
            $runs = $trigger->dueRuns($automation);
        } catch (AutomationNotRunnableException $exception) {
            $this->recordFailure($automation, new AutomationFailureDTO($exception->reasonCode));

            return;
        }

        $outcomes = [];

        foreach ($runs as $run) {
            $outcome = $this->send($automation, $run);
            $outcomes[] = $outcome;

            if ($outcome->isSettled()) {
                $trigger->settled($automation, $run);
            }
        }

        $this->clearSettledFailure($automation, $outcomes);
        $this->recordWaiting($automation, $trigger->waitingOn($automation));
    }

    /**
     * @param  CrmAutomationRunOutcome[]  $outcomes
     */
    private function clearSettledFailure(CrmAutomationDomainObject $automation, array $outcomes): void
    {
        $recorded = AutomationFailureDTO::decode($automation->getLastFailureReason())?->code;

        $isSettled = match (true) {
            in_array(CrmAutomationRunOutcome::REFUSED, $outcomes, true) => false,
            in_array(CrmAutomationRunOutcome::SENT, $outcomes, true) => true,
            in_array(CrmAutomationRunOutcome::IN_REVIEW, $outcomes, true) => $recorded !== AutomationFailureDTO::REVIEW_EXPIRED,
            in_array(CrmAutomationRunOutcome::NOBODY_TO_SEND_TO, $outcomes, true) => ! in_array($recorded, self::FAILURES_ONLY_A_SENT_EMAIL_SETTLES, true),
            default => false,
        };

        if ($isSettled) {
            $this->recordFailure($automation, null);
        }
    }

    private function triggerFor(CrmAutomationTrigger $trigger): AutomationTrigger
    {
        return match ($trigger) {
            CrmAutomationTrigger::EVENT_PUBLISHED => $this->eventPublished,
            CrmAutomationTrigger::AFTER_EVENT => $this->afterEvent,
            CrmAutomationTrigger::BEFORE_EVENT => $this->beforeEvent,
            CrmAutomationTrigger::ABANDONED_CHECKOUT => $this->abandonedCheckout,
            CrmAutomationTrigger::SEGMENT_ENTERED => $this->segmentEntered,
            CrmAutomationTrigger::WIN_BACK, CrmAutomationTrigger::WELCOME => $this->daily,
        };
    }

    /**
     * @throws Throwable
     */
    private function send(CrmAutomationDomainObject $automation, AutomationRunDTO $run): CrmAutomationRunOutcome
    {
        $values = $this->tokenValues($automation, $run->event);
        $campaign = $this->createDraft([
            ...$run->attributes,
            CrmCampaignDomainObjectAbstract::ACCOUNT_ID => $automation->getAccountId(),
            CrmCampaignDomainObjectAbstract::ORGANIZER_ID => $automation->getOrganizerId(),
            CrmCampaignDomainObjectAbstract::AUTOMATION_ID => $automation->getId(),
            CrmCampaignDomainObjectAbstract::AUTOMATION_RUN_KEY => $run->run_key,
            CrmCampaignDomainObjectAbstract::NAME => mb_substr(implode(' · ', array_filter([$automation->getName(), $run->label])), 0, 150),
            CrmCampaignDomainObjectAbstract::SUBJECT => mb_substr($this->tokenRenderer->renderSubject($automation->getSubject(), $values), 0, 200),
            CrmCampaignDomainObjectAbstract::PREHEADER => $automation->getPreheader() === null ? null : mb_substr($this->tokenRenderer->renderSubject($automation->getPreheader(), $values), 0, 150),
            CrmCampaignDomainObjectAbstract::BODY => (string) $this->sanitizer->sanitize($this->tokenRenderer->renderBody($automation->getBody(), $values)),
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::DRAFT->value,
        ]);

        if ($campaign === null) {
            return $run->contact_ids === null && $this->hasRunLaunched($automation, $run->run_key)
                ? CrmAutomationRunOutcome::ALREADY_LAUNCHED
                : CrmAutomationRunOutcome::LEFT_FOR_NEXT_RUN;
        }

        if ($run->contact_ids !== null) {
            $this->campaignRepository->addExplicitAudience($campaign->getId(), $run->contact_ids);
        }

        try {
            $status = $this->launchService->launch($campaign);
        } catch (NoMailableRecipientsException) {
            if ($run->keep_when_nobody_to_send_to) {
                $this->campaignRepository->transitionStatus($campaign->getId(), [CrmCampaignStatus::DRAFT->value], [
                    CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::CANCELLED->value,
                    CrmCampaignDomainObjectAbstract::STATUS_REASON => self::REASON_AUTOMATION_SKIPPED,
                ]);
            } else {
                $this->discardDraft($campaign);
            }

            return CrmAutomationRunOutcome::NOBODY_TO_SEND_TO;
        } catch (CampaignNotSendableException $exception) {
            $this->discardDraft($campaign);
            $this->recordFailure($automation, new AutomationFailureDTO($exception->reasonCode, $exception->reasonParams));

            return CrmAutomationRunOutcome::REFUSED;
        } catch (Throwable $exception) {
            $this->discardDraft($campaign);

            throw $exception;
        }

        return $status === CrmCampaignStatus::PENDING_REVIEW ? CrmAutomationRunOutcome::IN_REVIEW : CrmAutomationRunOutcome::SENT;
    }

    private function hasRunInReview(CrmAutomationDomainObject $automation): bool
    {
        return $this->campaignRepository->findFirstWhere([
            CrmCampaignDomainObjectAbstract::AUTOMATION_ID => $automation->getId(),
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::PENDING_REVIEW->value,
        ]) !== null;
    }

    private function createDraft(array $attributes): ?CrmCampaignDomainObject
    {
        try {
            return $this->databaseManager->transaction(fn () => $this->campaignRepository->create($attributes));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function discardOrphanedDrafts(CrmAutomationDomainObject $automation): void
    {
        $this->campaignRepository->deleteWhere([
            CrmCampaignDomainObjectAbstract::AUTOMATION_ID => $automation->getId(),
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::DRAFT->value,
            [CrmCampaignDomainObjectAbstract::CREATED_AT, '<', now()->subMinutes(CrmAutomationRepositoryInterface::ORPHANED_DRAFT_MINUTES)->toDateTimeString()],
        ]);
    }

    private function hasRunLaunched(CrmAutomationDomainObject $automation, string $runKey): bool
    {
        $campaign = $this->campaignRepository->findFirstWhere([
            CrmCampaignDomainObjectAbstract::AUTOMATION_ID => $automation->getId(),
            CrmCampaignDomainObjectAbstract::AUTOMATION_RUN_KEY => $runKey,
        ]);

        return $campaign !== null && $campaign->getStatus() !== CrmCampaignStatus::DRAFT->value;
    }

    private function discardDraft(CrmCampaignDomainObject $campaign): void
    {
        $this->campaignRepository->deleteWhere([
            CrmCampaignDomainObjectAbstract::ID => $campaign->getId(),
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::DRAFT->value,
        ]);
    }

    private function recordWaiting(CrmAutomationDomainObject $automation, ?AutomationFailureDTO $waitingOn): void
    {
        $recorded = AutomationFailureDTO::decode($automation->getLastFailureReason());

        if ($recorded === null || $recorded->code === AutomationFailureDTO::EVENT_AWAITING_REVIEW) {
            $this->recordFailure($automation, $waitingOn);
        }
    }

    private function recordFailure(CrmAutomationDomainObject $automation, ?AutomationFailureDTO $failure): void
    {
        $reason = $failure?->forStorage();

        if ($automation->getLastFailureReason() === $reason) {
            return;
        }

        $this->automationRepository->updateWhere(
            attributes: [CrmAutomationDomainObjectAbstract::LAST_FAILURE_REASON => $reason],
            where: [CrmAutomationDomainObjectAbstract::ID => $automation->getId()],
        );
        $automation->setLastFailureReason($reason);
    }

    /**
     * @return array<string, string|null>
     */
    private function tokenValues(CrmAutomationDomainObject $automation, ?EventDomainObject $event): array
    {
        $values = [
            'organizer_name' => $this->organizerRepository->findById($automation->getOrganizerId())->getName(),
            'event_title' => $event?->getTitle(),
        ];

        return $event === null ? [...$values, 'event_date' => null] : $values;
    }
}
