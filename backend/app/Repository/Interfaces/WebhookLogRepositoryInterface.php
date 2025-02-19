<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\WebhookLogDomainObject;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<WebhookLogDomainObject>
 */
interface WebhookLogRepositoryInterface extends RepositoryInterface
{
    public function deleteOldLogs(int $webhookId, int $numberToKeep = 20): void;
}
