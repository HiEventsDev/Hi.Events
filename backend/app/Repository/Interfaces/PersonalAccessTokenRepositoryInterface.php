<?php

namespace HiEvents\Repository\Interfaces;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<PersonalAccessTokenDomainObject>
 */
interface PersonalAccessTokenRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, PersonalAccessTokenDomainObject>
     */
    public function findDueForExpiryReminder(CarbonImmutable $now, int $windowDays, int $minimumLifetimeDays): Collection;
}
