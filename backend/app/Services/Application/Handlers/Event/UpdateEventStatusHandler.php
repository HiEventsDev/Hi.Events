<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Exceptions\EventPendingReviewException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Jobs\Event\Webhook\DispatchEventWebhookJob;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventStatusDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class UpdateEventStatusHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private AccountRepositoryInterface $accountRepository,
        private LoggerInterface $logger,
        private DatabaseManager $databaseManager,
        private EventSpamCheckService $eventSpamCheckService,
    ) {}

    /**
     * @throws AccountNotVerifiedException|EventPendingReviewException|Throwable
     */
    public function handle(UpdateEventStatusDTO $updateEventStatusDTO): EventDomainObject
    {
        return $this->databaseManager->transaction(function () use ($updateEventStatusDTO) {
            return $this->updateEventStatus($updateEventStatusDTO);
        });

    }

    /**
     * @throws AccountNotVerifiedException|EventPendingReviewException
     */
    private function updateEventStatus(UpdateEventStatusDTO $updateEventStatusDTO): EventDomainObject
    {
        $account = $this->accountRepository->findById($updateEventStatusDTO->accountId);

        if ($account->getAccountVerifiedAt() === null) {
            throw new AccountNotVerifiedException(
                __('You must verify your account before you can update an event\'s status.
                You can resend the confirmation by visiting your profile page.'),
            );
        }

        $event = $this->eventRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, nested: [
                new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ], name: 'event_location'),
            ]))
            ->findFirstWhere([
                'id' => $updateEventStatusDTO->eventId,
                'account_id' => $updateEventStatusDTO->accountId,
            ]);

        if ($event === null) {
            throw new ResourceNotFoundException(
                __('Event :id not found', ['id' => $updateEventStatusDTO->eventId]),
            );
        }

        if ($event->getStatus() === EventStatus::PENDING_MANUAL_REVIEW->name) {
            throw new EventPendingReviewException(
                __('This event is pending manual review and its status cannot be changed until the review is complete.'),
            );
        }

        $previousStatus = $event->getStatus();

        $this->eventRepository->updateWhere(
            attributes: ['status' => $updateEventStatusDTO->status],
            where: [
                'id' => $updateEventStatusDTO->eventId,
                'account_id' => $updateEventStatusDTO->accountId,
            ]
        );

        $event->setStatus($updateEventStatusDTO->status);

        $this->logger->info('Event status updated', [
            'eventId' => $updateEventStatusDTO->eventId,
            'status' => $updateEventStatusDTO->status,
        ]);

        $eventType = $updateEventStatusDTO->status === EventStatus::ARCHIVED->name
            ? DomainEventType::EVENT_ARCHIVED
            : DomainEventType::EVENT_UPDATED;

        DispatchEventWebhookJob::dispatch(
            $event->getId(),
            $eventType,
        );

        $isBecomingLive = $updateEventStatusDTO->status === EventStatus::LIVE->name
            && $previousStatus !== EventStatus::LIVE->name;

        if ($isBecomingLive && $this->eventSpamCheckService->isEnabled()) {
            EventSpamCheckJob::dispatch(
                $event->getId(),
                $this->eventSpamCheckService->hashContent($event->getTitle(), $event->getDescription()),
            )->afterCommit();
        }

        return $event;
    }
}
