<?php

namespace HiEvents\Services\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Ticket\TicketQuantityService;
use HiEvents\Services\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class PartialEditAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly TicketQuantityService       $ticketQuantityService,
        private readonly DatabaseManager             $databaseManager
    )
    {
    }

    /**
     * @throws Throwable|ResourceNotFoundException
     */
    public function handle(PartialEditAttendeeDTO $data): AttendeeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($data) {
            return $this->updateAttendee($data);
        });
    }

    private function updateAttendee(PartialEditAttendeeDTO $data): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            'id' => $data->attendee_id,
            'event_id' => $data->event_id,
        ]);

        if (!$attendee) {
            throw new ResourceNotFoundException();
        }

        //if status has changed, adjust ticket quantity
        if ($data->status && $data->status !== $attendee->getStatus()) {
            $this->ticketQuantityService->decreaseTicketPriceQuantitySold($attendee->getTicketPriceId());
            $this->ticketQuantityService->increaseTicketPriceQuantitySold($attendee->getTicketPriceId());
        }

        return $this->attendeeRepository->updateByIdWhere(
            id: $data->attendee_id,
            attributes: [
                'status' => $data->status
                    ? strtoupper($data->status)
                    : $attendee->getStatus(),
                'first_name' => $data->first_name ?? $attendee->getFirstName(),
                'last_name' => $data->last_name ?? $attendee->getLastName(),
                'email' => $data->email ?? $attendee->getEmail(),
            ],
            where: [
                'event_id' => $data->event_id,
            ]);
    }
}
