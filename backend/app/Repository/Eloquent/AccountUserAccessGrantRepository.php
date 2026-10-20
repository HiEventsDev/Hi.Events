<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountUserAccessGrantDomainObject;
use HiEvents\Models\AccountUserAccessGrant;
use HiEvents\Repository\Interfaces\AccountUserAccessGrantRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AccountUserAccessGrantDomainObject>
 */
class AccountUserAccessGrantRepository extends BaseRepository implements AccountUserAccessGrantRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountUserAccessGrant::class;
    }

    public function getDomainObject(): string
    {
        return AccountUserAccessGrantDomainObject::class;
    }

    public function findLiveGrantsForAccountUsers(array $accountUserIds): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->model
                ->whereIn('account_user_id', $accountUserIds)
                ->where(fn (Builder $query) => $query
                    ->whereNull('event_id')
                    ->orWhereIn('event_id', fn (QueryBuilder $events) => $events->select('id')->from('events')->whereNull('deleted_at')))
                ->where(fn (Builder $query) => $query
                    ->whereNull('organizer_id')
                    ->orWhereIn('organizer_id', fn (QueryBuilder $organizers) => $organizers->select('id')->from('organizers')->whereNull('deleted_at')))
                ->get()
        ));
    }
}
