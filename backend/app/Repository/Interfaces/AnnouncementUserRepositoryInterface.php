<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AnnouncementUserDomainObject;

/**
 * @extends RepositoryInterface<AnnouncementUserDomainObject>
 */
interface AnnouncementUserRepositoryInterface extends RepositoryInterface
{
    public function markSeen(array $announcementIds, int $userId): void;

    public function markDismissed(int $announcementId, int $userId): void;
}
