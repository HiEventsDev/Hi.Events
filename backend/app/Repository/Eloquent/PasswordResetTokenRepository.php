<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\PasswordResetTokenDomainObject;
use TicketKitten\Models\PasswordResetToken;
use TicketKitten\Repository\Interfaces\PasswordResetTokenRepositoryInterface;

class PasswordResetTokenRepository extends BaseRepository implements PasswordResetTokenRepositoryInterface
{
    protected function getModel(): string
    {
        return PasswordResetToken::class;
    }

    public function getDomainObject(): string
    {
        return PasswordResetTokenDomainObject::class;
    }
}
