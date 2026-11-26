<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmAutomationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\CampaignReviewExpiredException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Exceptions\NoMailableRecipientsException;
use HiEvents\Enterprise\Crm\Jobs\DispatchCampaignJob;
use HiEvents\Enterprise\Crm\Jobs\NotifyCampaignPendingReviewJob;
use HiEvents\Enterprise\Crm\Jobs\ReviewCampaignWithAiJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO\AutomationFailureDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\Trigger\BeforeEventTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignSendCheckDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\SendableAudienceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignAiReviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountSuspensionService;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

class CampaignLaunchService
{
    public const REASON_REJECTED_BY_REVIEW = 'REJECTED_BY_REVIEW';

    public const REASON_SCHEDULED_SEND_EXPIRED = 'SCHEDULED_SEND_EXPIRED';

    public const REASON_SCHEDULED_SEND_FAILED = 'SCHEDULED_SEND_FAILED';

    public const REASON_NO_RECIPIENTS = 'NO_RECIPIENTS';

    public const REASON_REVIEW_EXPIRED = 'REVIEW_EXPIRED';

    public const REASON_AUTOMATION_STOPPED = 'AUTOMATION_STOPPED';

    public const SCHEDULED_SEND_WINDOW_HOURS = 6;

    public const AUTOMATION_REVIEW_WINDOW_HOURS = 24;

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignSendGate $sendGate,
        private readonly CampaignAudienceService $audienceService,
        private readonly EarlyAccessService $earlyAccessService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly DatabaseManager $databaseManager,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CampaignHtmlSanitizer $sanitizer,
        private readonly CampaignOwnerNotifier $ownerNotifier,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly SmsConnectionService $smsConnectionService,
        private readonly CrmAutomationRepositoryInterface $automationRepository,
        private readonly CampaignAiReviewService $aiReviewService,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly AccountSuspensionService $accountSuspensionService,
    ) {}

    /**
     * @throws CampaignNotSendableException
     */
    private function assertSendable(CrmCampaignDomainObject $campaign): SendableAudienceDTO
    {
        $smsCountries = $campaign->isSms() ? $this->assertSmsReady($campaign) : [];

        if (! $campaign->isSms() && (trim((string) $campaign->getSubject()) === '' || trim(strip_tags((string) $campaign->getBody())) === '')) {
            throw new CampaignNotSendableException(__('Add a subject and a message before sending.'), CampaignNotSendableException::REASON_EMPTY_CONTENT);
        }

        $placeholderError = CampaignTokenCatalog::firstError(
            $campaign->getSubject().' '.$campaign->getPreheader().' '.$campaign->getBody(),
            $campaign->getEventId() !== null || $campaign->getEventOccurrenceId() !== null,
        );

        if ($placeholderError !== null) {
            throw new CampaignNotSendableException($placeholderError, CampaignNotSendableException::REASON_INVALID_PLACEHOLDER);
        }

        if ($campaign->offersEarlyAccess()) {
            $this->earlyAccessService->findOfferableTickets($campaign->getOrganizerId(), (int) $campaign->getEventId(), $campaign->earlyAccessProductIds());
        }

        try {
            $segment = $this->audienceService->compile($campaign);
        } catch (ResourceNotFoundException|InvalidSegmentDefinitionException) {
            throw new CampaignNotSendableException(__('The segment this campaign uses no longer exists. Choose who to send it to again.'), CampaignNotSendableException::REASON_SEGMENT_DELETED);
        }

        return new SendableAudienceDTO(
            segment: $segment,
            mailable_count: $campaign->isSms()
                ? $this->contactRepository->countTextable($campaign->getOrganizerId(), $segment, $smsCountries)
                : $this->audienceService->countCompiled($campaign->getOrganizerId(), $segment)->mailable_count,
            sms_countries: $smsCountries,
        );
    }

    /**
     * @return string[]
     *
     * @throws CampaignNotSendableException
     */
    private function assertSmsReady(CrmCampaignDomainObject $campaign): array
    {
        if (trim((string) $campaign->getBody()) === '') {
            throw new CampaignNotSendableException(__('Add a message before sending.'));
        }

        $this->assertTextMessagesAvailable($campaign);

        $countries = $this->smsConnectionService->enabledCountries($this->smsConnectionService->findForOrganizer($campaign->getOrganizerId()));

        if ($countries === []) {
            throw new CampaignNotSendableException(__('Connect Twilio and choose the countries you send to before sending text messages.'));
        }

        if ($campaign->earlyAccessProductIds() !== [] && ! in_array('event_url', CampaignTokenCatalog::used((string) $campaign->getBody()), true)) {
            throw new CampaignNotSendableException(__('Add the event link to your message so people can reach the early access tickets.'));
        }

        return $countries;
    }

    /**
     * @throws CampaignNotSendableException
     */
    private function assertTextMessagesAvailable(CrmCampaignDomainObject $campaign): void
    {
        if (! $this->featureFlagService->isEnabled(FeatureFlag::CRM_SMS, $campaign->getAccountId())) {
            throw new CampaignNotSendableException(__('Text messages aren\'t available for this account.'));
        }
    }

    public function check(CrmCampaignDomainObject $campaign, bool $isScheduled): CampaignSendCheckDTO
    {
        $mailableCount = 0;

        try {
            $audience = $this->assertSendable($campaign);
            $mailableCount = $audience->mailable_count;
            $decision = $this->sendGate->evaluate($campaign, $mailableCount, $audience->segment, checkDailyCap: ! $isScheduled);
        } catch (CampaignNotSendableException $exception) {
            return new CampaignSendCheckDTO(
                mailable_count: $mailableCount,
                requires_review: false,
                review_reason: null,
                blocked_reason: $exception->getMessage(),
                blocked_reason_code: $exception->reasonCode,
                blocked_reason_params: $exception->reasonParams ?: null,
            );
        }

        return new CampaignSendCheckDTO(
            mailable_count: $mailableCount,
            requires_review: $decision->requires_review,
            review_reason: $decision->review_reason,
            blocked_reason: null,
            warnings: $this->warnings($campaign),
            early_access_tickets: $this->earlyAccessTickets($campaign),
        );
    }

    /**
     * @return array<int, array{id: int, title: string, is_hidden: bool}>
     */
    private function earlyAccessTickets(CrmCampaignDomainObject $campaign): array
    {
        if (! $campaign->offersEarlyAccess()) {
            return [];
        }

        return $this->earlyAccessService->findTickets((int) $campaign->getEventId(), $campaign->earlyAccessProductIds())
            ->map(static fn (ProductDomainObject $product) => [
                'id' => $product->getId(),
                'title' => $product->getTitle(),
                'is_hidden' => (bool) $product->getIsHiddenWithoutPromoCode(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return string[]
     */
    private function warnings(CrmCampaignDomainObject $campaign): array
    {
        $warnings = [];
        $missingAlt = $campaign->isSms() ? 0 : $this->sanitizer->countImagesWithoutAlt($campaign->getBody());

        if ($missingAlt > 0) {
            $warnings[] = trans_choice(
                '{1} An image has no description. Add one so people who can\'t see images know what it shows.|[2,*] :count images have no description. Add one to each so people who can\'t see images know what they show.',
                $missingAlt,
                ['count' => $missingAlt],
            );
        }

        if ($campaign->getEventId() === null) {
            return $warnings;
        }

        $event = $this->eventRepository->findFirstWhere(['id' => $campaign->getEventId()]);

        if ($event === null) {
            $warnings[] = $campaign->isSms()
                ? __('The linked event was deleted, so the text message goes out without its event details.')
                : __('The linked event was deleted, so the email goes out without the event card.');
        } elseif ($event->getStatus() !== EventStatus::LIVE->name) {
            $warnings[] = $campaign->isSms()
                ? __('The linked event isn\'t published, so people who open the event link can\'t buy tickets yet.')
                : __('The linked event isn\'t published, so people who click the event card can\'t buy tickets yet.');
        } elseif (! $this->campaignRepository->hasCurrentOrUpcomingDate($event->getId(), now())) {
            $warnings[] = __('The linked event has already ended.');
        }

        return $warnings;
    }

    /**
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    public function launch(CrmCampaignDomainObject $campaign): CrmCampaignStatus
    {
        return $this->submit($campaign->getId(), null, null);
    }

    /**
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    public function schedule(CrmCampaignDomainObject $campaign, CarbonInterface $scheduledAt): CrmCampaignStatus
    {
        return $this->submit($campaign->getId(), $scheduledAt, null);
    }

    /**
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    private function submit(int $campaignId, ?CarbonInterface $scheduledAt, ?CarbonInterface $onlyIfScheduledBy): CrmCampaignStatus
    {
        $recipientsCount = 0;
        $decision = null;

        $status = $this->databaseManager->transaction(function () use ($campaignId, $scheduledAt, $onlyIfScheduledBy, &$recipientsCount, &$decision): CrmCampaignStatus {
            $campaign = $this->campaignRepository->findForLaunch($campaignId)
                ?? throw new CampaignNotSendableException(__('This campaign has already been sent or is being sent.'));
            $this->campaignRepository->lockAccountLaunches($campaign->getAccountId());

            $audience = $this->assertSendable($campaign);
            $decision = $this->sendGate->evaluate($campaign, $audience->mailable_count, $audience->segment, checkDailyCap: $scheduledAt === null);

            $status = match (true) {
                $decision->requires_review => CrmCampaignStatus::PENDING_REVIEW,
                $scheduledAt !== null => CrmCampaignStatus::SCHEDULED,
                default => CrmCampaignStatus::SENDING,
            };

            $moved = $this->campaignRepository->transitionStatus($campaign->getId(), $onlyIfScheduledBy === null ? CrmCampaignStatus::valuesOf(CrmCampaignStatus::launchable()) : [CrmCampaignStatus::SCHEDULED->value], [
                CrmCampaignDomainObjectAbstract::STATUS => $status->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => $decision->review_reason,
                CrmCampaignDomainObjectAbstract::REVIEW_NOTE => null,
                CrmCampaignDomainObjectAbstract::AI_REVIEW => null,
                CrmCampaignDomainObjectAbstract::APPROVED_AT => null,
                CrmCampaignDomainObjectAbstract::SCHEDULED_AT => $scheduledAt?->toDateTimeString(),
                CrmCampaignDomainObjectAbstract::LAUNCHED_AT => $scheduledAt === null ? now()->toDateTimeString() : null,
            ], $onlyIfScheduledBy);

            if (! $moved) {
                throw new CampaignNotSendableException(__('This campaign has already been sent or is being sent.'));
            }

            if ($status === CrmCampaignStatus::SCHEDULED) {
                return $status;
            }

            if ($status === CrmCampaignStatus::SENDING) {
                $this->startEarlyAccess($campaign);
            }

            $recipientsCount = $campaign->isSms()
                ? $this->campaignRepository->snapshotSmsRecipients($campaign->getId(), $campaign->getOrganizerId(), $audience->segment, now(), $audience->sms_countries)
                : $this->campaignRepository->snapshotRecipients($campaign->getId(), $campaign->getOrganizerId(), $audience->segment, now());

            if ($recipientsCount === 0) {
                throw new NoMailableRecipientsException($campaign->nobodyToSendToMessage());
            }

            return $status;
        });

        if ($status === CrmCampaignStatus::PENDING_REVIEW && $this->aiReviewService->isEnabled()) {
            ReviewCampaignWithAiJob::dispatch($campaignId, $recipientsCount, (string) $decision?->review_reason);
        } elseif ($status === CrmCampaignStatus::PENDING_REVIEW) {
            NotifyCampaignPendingReviewJob::dispatch($campaignId, $recipientsCount, (string) $decision?->review_reason);
        } elseif ($status === CrmCampaignStatus::SENDING) {
            DispatchCampaignJob::dispatch($campaignId);
        }

        return $status;
    }

    /**
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    private function startEarlyAccess(CrmCampaignDomainObject $campaign): void
    {
        if (! $campaign->offersEarlyAccess()) {
            return;
        }

        $grant = $this->earlyAccessService->createAccessCode($campaign->getOrganizerId(), $campaign->getEventId(), $campaign->earlyAccessProductIds());

        $this->campaignRepository->updateWhere(
            attributes: [
                CrmCampaignDomainObjectAbstract::PROMO_CODE_ID => $grant->promo_code_id,
                CrmCampaignDomainObjectAbstract::EARLY_ACCESS_HIDDEN_PRODUCT_IDS => json_encode($grant->hidden_product_ids),
            ],
            where: [CrmCampaignDomainObjectAbstract::ID => $campaign->getId()],
        );
    }

    public function launchScheduled(int $campaignId, CarbonInterface $now): void
    {
        $campaign = $this->campaignRepository->findFirstWhere([
            CrmCampaignDomainObjectAbstract::ID => $campaignId,
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::SCHEDULED->value,
        ]);

        $isDue = $campaign !== null && Carbon::parse($campaign->getScheduledAt())->lessThanOrEqualTo($now);

        if (! $isDue) {
            return;
        }

        $this->ownerNotifier->inOwnerLocale($campaign, function () use ($campaign, $now): void {
            if ($this->missedSendWindow($campaign, $now)) {
                $this->returnToDraft($campaign, CrmCampaignStatus::SCHEDULED, self::REASON_SCHEDULED_SEND_EXPIRED, $this->missedSendWindowMessage(), $now);

                return;
            }

            if (! $this->featureFlagService->isEnabled(FeatureFlag::CRM, $campaign->getAccountId())
                || $this->accountSuspensionService->isAccountSuspended($campaign->getAccountId())) {
                return;
            }

            try {
                if ($campaign->getApprovedAt() === null) {
                    $this->submit($campaign->getId(), null, $now);
                } else {
                    if ($campaign->isSms()) {
                        $this->assertTextMessagesAvailable($campaign);
                    }

                    $this->release($campaign, [CrmCampaignStatus::SCHEDULED->value], [], $now, null);
                }
            } catch (NoMailableRecipientsException $exception) {
                $this->returnToDraft($campaign, CrmCampaignStatus::SCHEDULED, self::REASON_NO_RECIPIENTS, $exception->getMessage(), $now);
            } catch (CampaignNotSendableException|UnrecognizedProductIdException $exception) {
                $this->returnToDraft($campaign, CrmCampaignStatus::SCHEDULED, self::REASON_SCHEDULED_SEND_FAILED, $exception->getMessage(), $now);
            }
        });
    }

    /**
     * @throws CampaignNotSendableException
     * @throws CampaignReviewExpiredException
     * @throws UnrecognizedProductIdException
     */
    public function approve(CrmCampaignDomainObject $campaign, CarbonInterface $reviewedVersion): bool
    {
        if ($this->expireStaleReview($campaign, now()) || $this->cancelRunOfStoppedAutomation($campaign)) {
            throw new CampaignReviewExpiredException;
        }

        $approval = [
            CrmCampaignDomainObjectAbstract::STATUS_REASON => null,
            CrmCampaignDomainObjectAbstract::APPROVED_AT => now()->toDateTimeString(),
        ];

        if ($campaign->getScheduledAt() !== null && Carbon::parse($campaign->getScheduledAt())->isFuture()) {
            return $this->campaignRepository->transitionStatus(
                campaignId: $campaign->getId(),
                fromStatuses: [CrmCampaignStatus::PENDING_REVIEW->value],
                attributes: [...$approval, CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::SCHEDULED->value],
                onlyIfLastUpdatedAt: $reviewedVersion,
            );
        }

        return $this->release($campaign, [CrmCampaignStatus::PENDING_REVIEW->value], $approval, null, $reviewedVersion);
    }

    public function expireStaleReview(CrmCampaignDomainObject $campaign, CarbonInterface $now): bool
    {
        if ($campaign->getStatus() !== CrmCampaignStatus::PENDING_REVIEW->value) {
            return false;
        }

        if ($campaign->getAutomationId() !== null) {
            return match (true) {
                $this->waitedTooLongForAutomationReview($campaign, $now) => $this->cancelUnreviewedAutomationRun($campaign, ['hours' => self::AUTOMATION_REVIEW_WINDOW_HOURS]),
                $this->isReminderForADateNoLongerComingUp($campaign, $now) => $this->cancelUnreviewedAutomationRun($campaign, []),
                default => false,
            };
        }

        if ($campaign->getScheduledAt() === null || ! $this->missedSendWindow($campaign, $now)) {
            return false;
        }

        return $this->ownerNotifier->inOwnerLocale($campaign, fn (): bool => $this->returnToDraft(
            $campaign,
            CrmCampaignStatus::PENDING_REVIEW,
            self::REASON_SCHEDULED_SEND_EXPIRED,
            $this->missedSendWindowMessage(),
            $now,
        ));
    }

    private function missedSendWindow(CrmCampaignDomainObject $campaign, CarbonInterface $now): bool
    {
        return Carbon::parse($campaign->getScheduledAt())->addHours(self::SCHEDULED_SEND_WINDOW_HOURS)->lessThan($now);
    }

    private function missedSendWindowMessage(): string
    {
        return trans_choice(
            '{1} It couldn\'t be sent within :count hour of its scheduled time. Schedule it again if you still want to send it.|[2,*] It couldn\'t be sent within :count hours of its scheduled time. Schedule it again if you still want to send it.',
            self::SCHEDULED_SEND_WINDOW_HOURS,
        );
    }

    private function waitedTooLongForAutomationReview(CrmCampaignDomainObject $campaign, CarbonInterface $now): bool
    {
        return Carbon::parse($campaign->getLaunchedAt() ?? $campaign->getCreatedAt())->addHours(self::AUTOMATION_REVIEW_WINDOW_HOURS)->lessThan($now);
    }

    private function isReminderForADateNoLongerComingUp(CrmCampaignDomainObject $campaign, CarbonInterface $now): bool
    {
        if ($campaign->getEventOccurrenceId() === null || ! str_starts_with((string) $campaign->getAutomationRunKey(), BeforeEventTrigger::RUN_KEY_PREFIX)) {
            return false;
        }

        $occurrence = $this->occurrenceRepository->findFirstWhere([EventOccurrenceDomainObjectAbstract::ID => $campaign->getEventOccurrenceId()]);
        $event = $occurrence === null ? null : $this->eventRepository->findFirstWhere(['id' => $occurrence->getEventId()]);

        return $event?->getStatus() !== EventStatus::LIVE->name
            || $occurrence->getStatus() !== EventOccurrenceStatus::ACTIVE->name
            || Carbon::parse($occurrence->getStartDate(), 'UTC')->lessThanOrEqualTo($now);
    }

    public function cancelRunsOfStoppedAutomation(int $automationId): void
    {
        $this->databaseManager->transaction(function () use ($automationId): void {
            $runs = $this->campaignRepository->findWhereIn(
                CrmCampaignDomainObjectAbstract::STATUS,
                CrmCampaignStatus::valuesOf([...CrmCampaignStatus::withdrawable(), ...CrmCampaignStatus::stoppable()]),
                [CrmCampaignDomainObjectAbstract::AUTOMATION_ID => $automationId],
            );

            foreach ($runs as $run) {
                $this->cancelRunOfStoppedAutomationNow($run);
            }
        });
    }

    private function cancelRunOfStoppedAutomation(CrmCampaignDomainObject $campaign): bool
    {
        if ($campaign->getAutomationId() === null) {
            return false;
        }

        $automation = $this->automationRepository->findFirstWhere([CrmAutomationDomainObjectAbstract::ID => $campaign->getAutomationId()]);

        if ($automation?->getIsEnabled() === true) {
            return false;
        }

        $this->databaseManager->transaction(fn () => $this->cancelRunOfStoppedAutomationNow($campaign));

        return true;
    }

    private function cancelRunOfStoppedAutomationNow(CrmCampaignDomainObject $run): void
    {
        $stopped = [
            CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::CANCELLED->value,
            CrmCampaignDomainObjectAbstract::STATUS_REASON => self::REASON_AUTOMATION_STOPPED,
        ];

        $withdrawn = $this->campaignRepository->transitionStatus($run->getId(), CrmCampaignStatus::valuesOf(CrmCampaignStatus::withdrawable()), [
            ...$stopped,
            CrmCampaignDomainObjectAbstract::SCHEDULED_AT => null,
            CrmCampaignDomainObjectAbstract::LAUNCHED_AT => null,
            CrmCampaignDomainObjectAbstract::APPROVED_AT => null,
        ]);

        if ($withdrawn) {
            $this->campaignRepository->withdrawRecipients($run->getId(), keepAsSkipped: true);

            return;
        }

        if ($this->campaignRepository->transitionStatus($run->getId(), CrmCampaignStatus::valuesOf(CrmCampaignStatus::stoppable()), $stopped)) {
            $this->campaignRepository->skipPendingRecipients($run->getId());
        }
    }

    /**
     * @param  array<string, int>  $failureParams
     */
    private function cancelUnreviewedAutomationRun(CrmCampaignDomainObject $campaign, array $failureParams): bool
    {
        return $this->databaseManager->transaction(function () use ($campaign, $failureParams): bool {
            $cancelled = $this->campaignRepository->transitionStatus($campaign->getId(), [CrmCampaignStatus::PENDING_REVIEW->value], [
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::CANCELLED->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => self::REASON_REVIEW_EXPIRED,
                CrmCampaignDomainObjectAbstract::LAUNCHED_AT => null,
            ]);

            if ($cancelled) {
                $this->campaignRepository->withdrawRecipients($campaign->getId(), keepAsSkipped: true);
                $this->automationRepository->updateWhere(
                    attributes: [
                        CrmAutomationDomainObjectAbstract::LAST_FAILURE_REASON => (new AutomationFailureDTO(AutomationFailureDTO::REVIEW_EXPIRED, $failureParams))->forStorage(),
                    ],
                    where: [CrmAutomationDomainObjectAbstract::ID => $campaign->getAutomationId()],
                );
            }

            return $cancelled;
        });
    }

    /**
     * @param  string[]  $fromStatuses
     * @param  array<string, mixed>  $attributes
     *
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    private function release(CrmCampaignDomainObject $campaign, array $fromStatuses, array $attributes, ?CarbonInterface $onlyIfScheduledBy, ?CarbonInterface $onlyIfLastUpdatedAt): bool
    {
        $released = $this->databaseManager->transaction(function () use ($campaign, $fromStatuses, $attributes, $onlyIfScheduledBy, $onlyIfLastUpdatedAt): bool {
            $moved = $this->campaignRepository->transitionStatus($campaign->getId(), $fromStatuses, [
                ...$attributes,
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::SENDING->value,
                CrmCampaignDomainObjectAbstract::SCHEDULED_AT => null,
                CrmCampaignDomainObjectAbstract::LAUNCHED_AT => $campaign->getLaunchedAt() ?? now()->toDateTimeString(),
            ], $onlyIfScheduledBy, $onlyIfLastUpdatedAt);

            if ($moved) {
                $this->startEarlyAccess($campaign);
            }

            return $moved;
        });

        if ($released) {
            DispatchCampaignJob::dispatch($campaign->getId());
        }

        return $released;
    }

    private function returnToDraft(CrmCampaignDomainObject $campaign, CrmCampaignStatus $from, string $reasonCode, string $reasonForOwner, CarbonInterface $now): bool
    {
        $returned = $this->databaseManager->transaction(function () use ($campaign, $from, $reasonCode, $now): bool {
            $returned = $this->campaignRepository->transitionStatus($campaign->getId(), [$from->value], [
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::DRAFT->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => $reasonCode,
                CrmCampaignDomainObjectAbstract::SCHEDULED_AT => null,
                CrmCampaignDomainObjectAbstract::APPROVED_AT => null,
                CrmCampaignDomainObjectAbstract::LAUNCHED_AT => null,
            ], $now);

            if ($returned) {
                $this->campaignRepository->withdrawRecipients($campaign->getId());
            }

            return $returned;
        });

        if ($returned) {
            $this->ownerNotifier->notifyNotSent($campaign, $reasonForOwner);
        }

        return $returned;
    }

    public function reject(CrmCampaignDomainObject $campaign, ?string $note): bool
    {
        return $this->databaseManager->transaction(function () use ($campaign, $note): bool {
            $rejected = $this->campaignRepository->transitionStatus($campaign->getId(), [CrmCampaignStatus::PENDING_REVIEW->value], [
                CrmCampaignDomainObjectAbstract::STATUS => $campaign->getAutomationId() === null
                    ? CrmCampaignStatus::REJECTED->value
                    : CrmCampaignStatus::CANCELLED->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => self::REASON_REJECTED_BY_REVIEW,
                CrmCampaignDomainObjectAbstract::REVIEW_NOTE => $note,
                CrmCampaignDomainObjectAbstract::LAUNCHED_AT => null,
                CrmCampaignDomainObjectAbstract::SCHEDULED_AT => null,
            ]);

            if ($rejected) {
                $this->campaignRepository->withdrawRecipients($campaign->getId(), keepAsSkipped: $campaign->getAutomationId() !== null);
            }

            return $rejected;
        });
    }
}
