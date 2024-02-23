<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Models\Organizer;
use TicketKitten\Repository\Interfaces\OrganizerRepositoryInterface;

class OrganizerRepository extends BaseRepository implements OrganizerRepositoryInterface
{
    protected function getModel(): string
    {
        return Organizer::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerDomainObject::class;
    }
}
