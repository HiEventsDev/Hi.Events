<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\WebhookLogDomainObject;

/**
 * @extends RepositoryInterface<WebhookLogDomainObject>
 */
interface WebhookLogRepositoryInterface extends RepositoryInterface
{
    public function deleteOldLogs(int $webhookId, int $numberToKeep = 20): void;
}
