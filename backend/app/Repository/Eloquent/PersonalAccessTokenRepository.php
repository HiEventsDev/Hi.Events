<?php

namespace HiEvents\Repository\Eloquent;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\PersonalAccessToken;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
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
                ->orderBy(PersonalAccessTokenDomainObject::ID)
                ->get());
        });
    }
}
