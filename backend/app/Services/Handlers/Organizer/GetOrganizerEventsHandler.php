<?php

namespace HiEvents\Services\Handlers\Organizer;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Handlers\Organizer\DTO\GetOrganizerEventsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetOrganizerEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository
    )
    {
    }

    public function handle(GetOrganizerEventsDTO $dto): LengthAwarePaginator
    {
        return $this->eventRepository
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer',
            ))
            ->findEvents(
                where: [
                    'account_id' => $dto->accountId,
                    'organizer_id' => $dto->organizerId,
                ],
                params: $dto->queryParams
            );
    }
}
