<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PasswordResetTokenDomainObject;
use HiEvents\Models\PasswordResetToken;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;

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
