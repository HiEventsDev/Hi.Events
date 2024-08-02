<?php

namespace HiEvents\Services\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TicketType;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketDomainObjectAbstract;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Services\Domain\Ticket\TicketQuantityUpdateService;
use HiEvents\Services\Handlers\Attendee\DTO\EditAttendeeDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly TicketRepositoryInterface   $ticketRepository,
        private readonly TicketQuantityUpdateService $ticketQuantityService,
        private readonly DatabaseManager             $databaseManager,
    )
    {
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(EditAttendeeDTO $editAttendeeDTO): AttendeeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($editAttendeeDTO) {
            $this->validateTicketId($editAttendeeDTO);

            $attendee = $this->getAttendee($editAttendeeDTO);

            $this->adjustTicketQuantities($attendee, $editAttendeeDTO);

            return $this->updateAttendee($editAttendeeDTO);
        });
    }

    private function adjustTicketQuantities(AttendeeDomainObject $attendee, EditAttendeeDTO $editAttendeeDTO): void
    {
        if ($attendee->getTicketPriceId() !== $editAttendeeDTO->ticket_price_id) {
            $this->ticketQuantityService->decreaseQuantitySold($editAttendeeDTO->ticket_price_id);
            $this->ticketQuantityService->increaseQuantitySold($attendee->getTicketPriceId());
        }
    }

    private function updateAttendee(EditAttendeeDTO $editAttendeeDTO): AttendeeDomainObject
    {
        return $this->attendeeRepository->updateByIdWhere($editAttendeeDTO->attendee_id, [
            'first_name' => $editAttendeeDTO->first_name,
            'last_name' => $editAttendeeDTO->last_name,
            'email' => $editAttendeeDTO->email,
            'ticket_id' => $editAttendeeDTO->ticket_id,
        ], [
            'event_id' => $editAttendeeDTO->event_id,
        ]);
    }

    /**
     * @throws ValidationException
     * @throws NoTicketsAvailableException
     */
    private function validateTicketId(EditAttendeeDTO $editAttendeeDTO): void
    {
        $ticket = $this->ticketRepository
            ->loadRelation(TicketPriceDomainObject::class)
            ->findFirstWhere([
                TicketDomainObjectAbstract::ID => $editAttendeeDTO->ticket_id,
            ]);

        if ($ticket->getEventId() !== $editAttendeeDTO->event_id) {
            throw ValidationException::withMessages([
                'ticket_id' => __('Ticket ID is not valid'),
            ]);
        }

        $availableQuantity = $this->ticketRepository->getQuantityRemainingForTicketPrice(
            ticketId: $editAttendeeDTO->ticket_id,
            ticketPriceId: $ticket->getType() === TicketType::TIERED->name
                ? $editAttendeeDTO->ticket_price_id
                : $ticket->getTicketPrices()->first()->getId(),
        );

        if ($availableQuantity <= 0) {
            throw new NoTicketsAvailableException(
                __('There are no tickets available. If you would like to assign this ticket to this attendee, please adjust the ticket\'s available quantity.')
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function getAttendee(EditAttendeeDTO $editAttendeeDTO): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::EVENT_ID => $editAttendeeDTO->event_id,
            AttendeeDomainObjectAbstract::ID => $editAttendeeDTO->attendee_id,
        ]);

        if ($attendee === null) {
            throw ValidationException::withMessages([
                'attendee_id' => __('Attendee ID is not valid'),
            ]);
        }

        return $attendee;
    }
}
