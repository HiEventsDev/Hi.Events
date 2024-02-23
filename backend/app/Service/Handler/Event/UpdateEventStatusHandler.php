<?php

namespace TicketKitten\Service\Handler\Event;

use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\AccountNotVerifiedException;
use TicketKitten\Http\DataTransferObjects\UpdateEventStatusDTO;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;

readonly class UpdateEventStatusHandler
{
    public function __construct(
        private EventRepositoryInterface   $eventRepository,
        private AccountRepositoryInterface $accountRepository,
        private LoggerInterface            $logger,
        private DatabaseManager            $databaseManager,
    )
    {
    }

    /**
     * @throws AccountNotVerifiedException|Throwable
     */
    public function handle(UpdateEventStatusDTO $updateEventStatusDTO): EventDomainObject
    {
        return $this->databaseManager->transaction(function () use ($updateEventStatusDTO) {
            return $this->updateEventStatus($updateEventStatusDTO);
        });

    }

    /**
     * @throws AccountNotVerifiedException
     */
    private function updateEventStatus(UpdateEventStatusDTO $updateEventStatusDTO): EventDomainObject
    {
        $account = $this->accountRepository->findById($updateEventStatusDTO->accountId);

        if ($account->getAccountVerifiedAt() === null) {
            throw new AccountNotVerifiedException(
                __('You must verify your account before you can update an event\'s status')
            );
        }

        $this->eventRepository->updateWhere(
            attributes: ['status' => $updateEventStatusDTO->status],
            where: ['id' => $updateEventStatusDTO->eventId]
        );

        $this->logger->info('Event status updated', [
            'eventId' => $updateEventStatusDTO->eventId,
            'status' => $updateEventStatusDTO->status
        ]);

        return $this->eventRepository->findById($updateEventStatusDTO->eventId);
    }
}
