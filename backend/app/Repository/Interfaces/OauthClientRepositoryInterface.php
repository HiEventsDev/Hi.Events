<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\OauthClientDomainObject;

/**
 * @extends RepositoryInterface<OauthClientDomainObject>
 */
interface OauthClientRepositoryInterface extends RepositoryInterface
{
    public function deleteIdleWithoutConnections(CarbonInterface $idleSince): int;
}
