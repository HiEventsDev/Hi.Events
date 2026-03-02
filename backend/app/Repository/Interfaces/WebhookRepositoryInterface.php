<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\WebhookDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<WebhookDomainObject>
 */
interface WebhookRepositoryInterface extends RepositoryInterface
{
    public function findEnabledByEventId(int $eventId): Collection;
}
