<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\Models\OauthClient;
use HiEvents\Repository\Interfaces\OauthClientRepositoryInterface;
use Illuminate\Database\Query\Builder;

/**
 * @extends BaseRepository<OauthClientDomainObject>
 */
class OauthClientRepository extends BaseRepository implements OauthClientRepositoryInterface
{
    protected function getModel(): string
    {
        return OauthClient::class;
    }

    public function getDomainObject(): string
    {
        return OauthClientDomainObject::class;
    }

    public function deleteIdleWithoutConnections(CarbonInterface $idleSince): int
    {
        return $this->runQuery(fn () => $this->model
            ->whereRaw('COALESCE(last_used_at, created_at) < ?', [$idleSince])
            ->whereNotExists(static fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('oauth_connections')
                ->whereColumn('oauth_connections.oauth_client_id', 'oauth_clients.id'))
            ->delete());
    }
}
