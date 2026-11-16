<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrganizerSettingDomainObject;

/**
 * @extends RepositoryInterface<OrganizerSettingDomainObject>
 */
interface OrganizerSettingsRepositoryInterface extends RepositoryInterface
{
    public function setNewEventNotificationsEnabled(int $organizerId, bool $enabled): void;
}
