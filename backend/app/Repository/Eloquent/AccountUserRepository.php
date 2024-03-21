<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\Models\AccountUser;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;

class AccountUserRepository extends BaseRepository implements AccountUserRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountUser::class;
    }

    public function getDomainObject(): string
    {
        return AccountUserDomainObject::class;
    }
}
