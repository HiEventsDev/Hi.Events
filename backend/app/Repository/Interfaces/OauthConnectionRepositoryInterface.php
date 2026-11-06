<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OauthConnectionDomainObject;

/**
 * @extends RepositoryInterface<OauthConnectionDomainObject>
 */
interface OauthConnectionRepositoryInterface extends RepositoryInterface
{
    public function deleteWithoutRefreshTokens(): int;
}
