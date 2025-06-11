<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class GetPublicOrganizerHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository
    )
    {
    }

    public function handle(int $organizerId)
    {
        return $this->organizerRepository
            ->loadRelation(ImageDomainObject::class)
            ->loadRelation(OrganizerSettingDomainObject::class)
            ->findById($organizerId);
    }
}
