<?php

namespace HiEvents\Repository\Eloquent;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\PersonalAccessToken;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<PersonalAccessTokenDomainObject>
 */
class PersonalAccessTokenRepository extends BaseRepository implements PersonalAccessTokenRepositoryInterface
{
    protected function getModel(): string
    {
        return PersonalAccessToken::class;
    }

    public function getDomainObject(): string
    {
        return PersonalAccessTokenDomainObject::class;
    }

    public function findDueForExpiryReminder(CarbonImmutable $now, int $windowDays, int $minimumLifetimeDays): Collection
    {
        return $this->runQuery(function () use ($now, $windowDays, $minimumLifetimeDays) {
            $this->loadRelation(new Relationship(UserDomainObject::class, name: 'user'));

            return $this->handleResults($this->model
                ->whereNull(PersonalAccessTokenDomainObject::EXPIRY_REMINDER_SENT_AT)
                ->where(PersonalAccessTokenDomainObject::EXPIRES_AT, '>', $now)
                ->where(PersonalAccessTokenDomainObject::EXPIRES_AT, '<=', $now->addDays($windowDays))
                ->whereRaw('created_at <= expires_at - make_interval(days => ?)', [$minimumLifetimeDays])
                ->whereNotExists(static fn (Builder $query) => $query
                    ->selectRaw('1')
                    ->from('account_deletion_requests')
                    ->whereColumn('account_deletion_requests.account_id', 'personal_access_tokens.account_id')
                    ->where('account_deletion_requests.status', AccountDeletionRequestStatus::REQUESTED->name))
                ->orderBy(PersonalAccessTokenDomainObject::ID)
                ->get());
        });
    }
}
