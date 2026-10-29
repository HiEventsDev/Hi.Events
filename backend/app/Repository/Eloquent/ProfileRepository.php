<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Models\Profile;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;

/**
 * @extends BaseRepository<ProfileDomainObject>
 */
class ProfileRepository extends BaseRepository implements ProfileRepositoryInterface
{
    protected function getModel(): string
    {
        return Profile::class;
    }

    public function getDomainObject(): string
    {
        return ProfileDomainObject::class;
    }
}
