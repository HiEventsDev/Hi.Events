<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OauthConnectionDomainObject;
use HiEvents\Models\OauthConnection;
use HiEvents\Repository\Interfaces\OauthConnectionRepositoryInterface;
use Illuminate\Database\Query\Builder;

/**
 * @extends BaseRepository<OauthConnectionDomainObject>
 */
class OauthConnectionRepository extends BaseRepository implements OauthConnectionRepositoryInterface
{
    protected function getModel(): string
    {
        return OauthConnection::class;
    }

    public function getDomainObject(): string
    {
        return OauthConnectionDomainObject::class;
    }

    public function deleteWithoutRefreshTokens(): int
    {
        return $this->runQuery(fn () => $this->model
            ->whereNotExists(static fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('oauth_refresh_tokens')
                ->whereColumn('oauth_refresh_tokens.oauth_connection_id', 'oauth_connections.id'))
            ->delete());
    }
}
