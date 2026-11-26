<?php

namespace HiEvents\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleContextDTO;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\AttendeeEvent;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class TicketResaleListingService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly TransactionLockService $transactionLockService,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly TicketResaleContextService $contextService,
        private readonly TicketResaleEligibilityService $eligibilityService,
        private readonly TicketResaleEstimateService $estimateService,
        private readonly TicketResaleNotificationService $notificationService,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function list(TicketResaleContextDTO $context, ?string $ipAddress, ?string $userAgent): TicketResaleListingDomainObject
    {
        $listing = $this->databaseManager->transaction(function () use ($context, $ipAddress, $userAgent) {
            $lockedContext = $this->contextUnderLocks($context);
            $block = $this->eligibilityService->blockOnListing($lockedContext);

            if ($block !== null) {
                throw new ResourceConflictException($block->message());
            }

            $currency = $lockedContext->order->getCurrency();

            $listing = $this->listingRepository->create([
                TicketResaleListingDomainObjectAbstract::EVENT_ID => $lockedContext->event->getId(),
                TicketResaleListingDomainObjectAbstract::ORDER_ID => $lockedContext->order->getId(),
                TicketResaleListingDomainObjectAbstract::ATTENDEE_ID => $lockedContext->attendee->getId(),
                TicketResaleListingDomainObjectAbstract::PRODUCT_PRICE_ID => $lockedContext->attendee->getProductPriceId(),
                TicketResaleListingDomainObjectAbstract::EVENT_OCCURRENCE_ID => $lockedContext->attendee->getEventOccurrenceId(),
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
                TicketResaleListingDomainObjectAbstract::MIN_UNIT_PRICE => min($lockedContext->seller_unit_price, $lockedContext->current_unit_price),
                TicketResaleListingDomainObjectAbstract::ESTIMATED_REFUND => Currency::fromMinorUnits(
                    $this->estimateService->estimate($lockedContext)->estimated_refund,
                    $currency,
                ),
                TicketResaleListingDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $lockedContext->connected_account_id,
            ]);

            $this->orderAuditLogService->logTicketResale(OrderAuditAction::TICKET_RESALE_LISTED, $listing, $ipAddress, $userAgent);

            return $listing;
        });

        $this->notificationService->notifySeller(TicketResaleNotice::LISTED_FOR_SELLER, $listing, $listing->getEstimatedRefund());
        $this->notificationService->notifyHolder(TicketResaleNotice::LISTED_FOR_HOLDER, $listing);

        $this->domainEventDispatcherService->dispatch(
            new AttendeeEvent(DomainEventType::ATTENDEE_RESALE_LISTED, $listing->getAttendeeId())
        );

        event(new CapacityChangedEvent(
            eventId: $listing->getEventId(),
            direction: CapacityChangeDirection::INCREASED,
            productId: $context->attendee->getProductId(),
            productPriceId: $listing->getProductPriceId(),
            eventOccurrenceId: $listing->getEventOccurrenceId(),
        ));

        return $listing;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function withdraw(TicketResaleContextDTO $context, ?string $ipAddress, ?string $userAgent): TicketResaleListingDomainObject
    {
        $listing = $this->withdrawUnderLock($context, $ipAddress, $userAgent);

        if ($listing->getStatus() === TicketResaleListingStatus::CLOSED->name) {
            $this->notificationService->notifyHolder(TicketResaleNotice::RETURNED_FOR_HOLDER, $listing);
        }

        return $listing;
    }

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    private function withdrawUnderLock(TicketResaleContextDTO $context, ?string $ipAddress, ?string $userAgent): TicketResaleListingDomainObject
    {
        return $this->databaseManager->transaction(function () use ($context, $ipAddress, $userAgent) {
            $this->lockTicket($context);

            $listing = $this->listingRepository->findListedForAttendeeLocked($context->attendee->getId());

            if ($listing === null) {
                throw new ResourceNotFoundException(__('This ticket is not listed for resale.'));
            }

            if ($this->listingRepository->hasLiveClaim($listing->getId())) {
                return $listing->getWithdrawalRequestedAt() !== null
                    ? $listing
                    : $this->recordWithdrawal($listing, [
                        TicketResaleListingDomainObjectAbstract::WITHDRAWAL_REQUESTED_AT => Carbon::now()->toDateTimeString(),
                    ], $ipAddress, $userAgent);
            }

            return $this->recordWithdrawal($listing, [
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::CLOSED->name,
                TicketResaleListingDomainObjectAbstract::CLOSED_REASON => TicketResaleCloseReason::WITHDRAWN->value,
            ], $ipAddress, $userAgent);
        });
    }

    private function recordWithdrawal(
        TicketResaleListingDomainObject $listing,
        array $changes,
        ?string $ipAddress,
        ?string $userAgent,
    ): TicketResaleListingDomainObject {
        $withdrawn = $this->listingRepository->updateFromArray($listing->getId(), $changes);

        $this->orderAuditLogService->logTicketResale(OrderAuditAction::TICKET_RESALE_WITHDRAWN, $withdrawn, $ipAddress, $userAgent);

        return $withdrawn;
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function contextUnderLocks(TicketResaleContextDTO $context): TicketResaleContextDTO
    {
        $this->lockTicket($context);

        return $this->contextService->forAttendeeId($context->attendee->getId());
    }

    private function lockTicket(TicketResaleContextDTO $context): void
    {
        $this->transactionLockService->lockEvent($context->event->getId());
        $this->attendeeRepository->findByIdLocked($context->attendee->getId());
    }
}
