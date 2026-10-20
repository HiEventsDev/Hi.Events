<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;

/**
 * @extends RepositoryInterface<OrganizerVatSettingDomainObject>
 */
interface OrganizerVatSettingRepositoryInterface extends RepositoryInterface
{
    public function findByOrganizerId(int $organizerId): ?OrganizerVatSettingDomainObject;
}
