<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\Models\AttendeeCheckIn;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;

class AttendeeCheckInRepository extends BaseRepository implements AttendeeCheckInRepositoryInterface
{
    protected function getModel(): string
    {
        return AttendeeCheckIn::class;
    }

    public function getDomainObject(): string
    {
        return AttendeeCheckInDomainObject::class;
    }
}
