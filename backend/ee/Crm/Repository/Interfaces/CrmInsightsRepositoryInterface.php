<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmContactDomainObject>
 */
interface CrmInsightsRepositoryInterface extends RepositoryInterface
{
    public function getAudienceTotals(int $organizerId, CarbonInterface $now): object;

    /**
     * @return Collection<int, object>
     */
    public function getMonthlyBuyers(int $organizerId, string $timezone, CarbonInterface $since): Collection;

    /**
     * @return Collection<int, object>
     */
    public function getRevenueConcentration(int $organizerId): Collection;

    /**
     * @return Collection<int, object>
     */
    public function getRetentionCohorts(int $organizerId, string $timezone, CarbonInterface $since): Collection;

    /**
     * @return Collection<int, object>
     */
    public function getLeadTimeBuckets(int $organizerId): Collection;

    public function eventBelongsToOrganizer(int $organizerId, int $eventId): bool;

    public function getEventAudience(int $organizerId, int $eventId): object;

    /**
     * @return Collection<int, object>
     */
    public function getEventTopContacts(int $organizerId, int $eventId, int $limit): Collection;

    /**
     * @return Collection<int, object>
     */
    public function getEventOverlaps(int $organizerId, int $eventId, int $limit): Collection;

    public function getExpectedTurnout(int $organizerId, int $eventId, CarbonInterface $now): object;

    /**
     * @param  int[]  $includeIds
     * @return Collection<int, object>
     */
    public function getEventOptions(int $organizerId, ?string $query, array $includeIds, int $limit): Collection;

    /**
     * @param  int[]  $includeIds
     * @return Collection<int, object>
     */
    public function getProductOptions(int $organizerId, ?string $query, array $includeIds, int $limit): Collection;

    /**
     * @return object{live_events_count: int, collecting_count: int}
     */
    public function getLiveEventOptInCounts(int $organizerId, CarbonInterface $now): object;

    /**
     * @return Collection<int, object{id: int, title: string}>
     */
    public function findLiveEventsNotCollectingOptIn(int $organizerId, CarbonInterface $now, int $limit): Collection;

    /**
     * @return Collection<int, object>
     */
    public function getLocationOptions(int $organizerId): Collection;

    /**
     * @return string[]
     */
    public function getCurrencies(int $organizerId): array;
}
