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
                ->where(fn (Builder $query) => $this->targetIsLive($query, 'event_id', 'events'))
                ->where(fn (Builder $query) => $this->targetIsLive($query, 'organizer_id', 'organizers'))
                ->get()
        ));
    }

    private function targetIsLive(Builder $query, string $column, string $targetTable): Builder
    {
        $grantColumn = $query->qualifyColumn($column);

        return $query
            ->whereNull($grantColumn)
            ->orWhereExists(fn (QueryBuilder $target) => $target
                ->selectRaw('1')
                ->from($targetTable)
                ->whereColumn("$targetTable.id", $grantColumn)
                ->whereNull("$targetTable.deleted_at"));
    }
}
