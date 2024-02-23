<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\PasswordResetDomainObject;
use TicketKitten\Models\PasswordReset;
use TicketKitten\Repository\Interfaces\PasswordResetRepositoryInterface;

class PasswordResetRepository extends BaseRepository implements PasswordResetRepositoryInterface
{
    protected function getModel(): string
    {
        return PasswordReset::class;
    }

    public function getDomainObject(): string
    {
        return PasswordResetDomainObject::class;
    }
}
