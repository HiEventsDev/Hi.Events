<?php

namespace HiEvents\Services\Application\Handlers\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Services\Application\Handlers\CheckInList\DTO\UpsertCheckInListDTO;
use HiEvents\Services\Domain\CheckInList\UpdateCheckInListService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;

class UpdateCheckInlistHandler
{
    public function __construct(
        private readonly UpdateCheckInlistService $updateCheckInlistService,
    )
    {
    }

    /**
     * @throws UnrecognizedProductIdException
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
            productIds: $data->productIds
        );
    }
}
