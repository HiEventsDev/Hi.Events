<?php

namespace HiEvents\Services\Handlers\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Services\Domain\CheckInList\UpdateCheckInListService;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use HiEvents\Services\Handlers\CheckInList\DTO\UpsertCheckInListDTO;

class UpdateCheckInlistHandler
{
    public function __construct(
        private readonly UpdateCheckInlistService $updateCheckInlistService,
    )
    {
    }

    /**
     * @throws UnrecognizedTicketIdException
     */
    public function handle(UpsertCheckInListDTO $data): CheckInListDomainObject
    {
        $checkInList = (new CheckInListDomainObject())
            ->setId($data->id)
            ->setName($data->name)
            ->setDescription($data->description)
            ->setEventId($data->eventId)
            ->setExpiresAt($data->expiresAt)
            ->setActivatesAt($data->activatesAt);

        return $this->updateCheckInlistService->updateCheckInlist(
            checkInList: $checkInList,
            ticketIds: $data->ticketIds
        );
    }
}
