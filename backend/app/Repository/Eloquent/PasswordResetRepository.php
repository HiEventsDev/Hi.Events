<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PasswordResetDomainObject;
use HiEvents\Models\PasswordReset;
use HiEvents\Repository\Interfaces\PasswordResetRepositoryInterface;

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
