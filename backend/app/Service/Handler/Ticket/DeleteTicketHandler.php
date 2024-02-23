<?php

namespace TicketKitten\Service\Handler\Ticket;

use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;
use TicketKitten\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\TicketDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\TicketPriceDomainObjectAbstract;
use TicketKitten\Exceptions\CannotDeleteEntityException;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketPriceRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;

readonly class DeleteTicketHandler
{
    public function __construct(
        private TicketRepositoryInterface      $ticketRepository,
        private AttendeeRepositoryInterface    $attendeeRepository,
        private TicketPriceRepositoryInterface $ticketPriceRepository,
        private LoggerInterface                $logger,
        private DatabaseManager                $databaseManager,
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function handle(int $ticketId, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($ticketId, $eventId) {
            $this->deleteTicket($ticketId, $eventId);
        });
    }

    /**
     * @throws CannotDeleteEntityException
     */
    private function deleteTicket(int $ticketId, int $eventId): void
    {
        $attendees = $this->attendeeRepository->findWhere(
            [
                AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
                AttendeeDomainObjectAbstract::TICKET_ID => $ticketId,
            ]
        );

        if ($attendees->count() > 0) {
            throw new CannotDeleteEntityException(
                __('You cannot delete this ticket because it has orders associated with it. You can hide it instead.')
            );
        }

        $this->ticketRepository->deleteWhere(
            [
                TicketDomainObjectAbstract::EVENT_ID => $eventId,
                TicketDomainObjectAbstract::ID => $ticketId,
            ]
        );

        $this->ticketPriceRepository->deleteWhere(
            [
                TicketPriceDomainObjectAbstract::TICKET_ID => $ticketId,
            ]
        );

        $this->logger->info(sprintf('Ticket %d was deleted from event %d', $ticketId, $eventId), [
            'ticketId' => $ticketId,
            'eventId' => $eventId,
        ]);
    }
}
