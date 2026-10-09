<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\UserTrustedDeviceDomainObject;
use HiEvents\Models\UserTrustedDevice;
use HiEvents\Repository\Interfaces\UserTrustedDeviceRepositoryInterface;

/**
 * @extends BaseRepository<UserTrustedDeviceDomainObject>
 */
class UserTrustedDeviceRepository extends BaseRepository implements UserTrustedDeviceRepositoryInterface
{
    protected function getModel(): string
    {
        return UserTrustedDevice::class;
    }

    public function getDomainObject(): string
    {
        return UserTrustedDeviceDomainObject::class;
    }
}
