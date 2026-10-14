<?php

namespace HiEvents\Repository\Interfaces;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SuspiciousActivityRepositoryInterface
{
    public function countAccountsCreatedBetween(CarbonInterface $from, CarbonInterface $to): int;

    public function findRecentAccountNamesAndEmailDomains(CarbonInterface $since, int $limit): Collection;

    public function findUsersWithSuspiciousNames(CarbonInterface $since, string $pattern, int $limit): Collection;

    public function findAccountsWithSuspiciousNames(CarbonInterface $since, string $pattern, int $limit): Collection;

    public function findOrganizersWithSuspiciousNames(CarbonInterface $since, string $pattern, int $limit): Collection;

    public function findEventsWithSuspiciousTitles(CarbonInterface $since, string $pattern, int $limit): Collection;

    public function findAccountsWithInviteBursts(CarbonInterface $since, int $threshold): Collection;

    public function findUnpublishedEventsWithOrderBursts(CarbonInterface $since, array $statuses, int $attendeeThreshold): Collection;

    public function findEventsWithFailedPaymentBursts(CarbonInterface $since, int $threshold): Collection;

    public function findEventsWithWaitlistBursts(CarbonInterface $since, int $threshold): Collection;

    public function findLiveEventsWithoutSpamCheck(CarbonInterface $createdAfter, CarbonInterface $createdBefore, int $limit): Collection;

    public function findEmailCategoryStats(CarbonInterface $since): Collection;
}
