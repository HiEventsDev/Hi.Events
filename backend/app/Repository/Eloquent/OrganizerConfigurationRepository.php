<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Models\OrganizerConfiguration;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;

/**
 * @extends BaseRepository<OrganizerConfigurationDomainObject>
 */
class OrganizerConfigurationRepository extends BaseRepository implements OrganizerConfigurationRepositoryInterface
{
    protected function getModel(): string
    {
        return OrganizerConfiguration::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerConfigurationDomainObject::class;
    }
}
