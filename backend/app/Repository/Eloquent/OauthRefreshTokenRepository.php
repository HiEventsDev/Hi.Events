<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OauthRefreshTokenDomainObject;
use HiEvents\Models\OauthRefreshToken;
use HiEvents\Repository\Interfaces\OauthRefreshTokenRepositoryInterface;

/**
 * @extends BaseRepository<OauthRefreshTokenDomainObject>
 */
class OauthRefreshTokenRepository extends BaseRepository implements OauthRefreshTokenRepositoryInterface
{
    protected function getModel(): string
    {
        return OauthRefreshToken::class;
    }

    public function getDomainObject(): string
    {
        return OauthRefreshTokenDomainObject::class;
    }
}
