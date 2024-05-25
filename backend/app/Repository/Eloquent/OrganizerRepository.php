<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Models\Organizer;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

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
