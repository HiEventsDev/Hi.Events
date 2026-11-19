<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmAutomationDomainObject>
 */
interface CrmAutomationRepositoryInterface extends RepositoryInterface
{
    public const ORPHANED_DRAFT_MINUTES = 10;

    /**
     * @return Collection<int, CrmAutomationDomainObject>
     */
    public function findByOrganizerIdWithStats(int $organizerId): Collection;

    /**
     * @return Collection<int, int> account ids keyed by automation id
     */
    public function findEnabledAccountIdsByIdAfter(int $afterId, int $limit): Collection;

    /**
     * @return int[]
     */
    public function findLiveUpcomingEventIds(int $organizerId, CarbonInterface $now): array;

    /**
     * @return int[]
     */
    public function findPublishedEventIds(int $organizerId): array;

    public function findNewlyLiveEventIds(CrmAutomationDomainObject $automation, CarbonInterface $settledBefore, bool $mustHavePassedSpamCheck): array;

    /**
     * @return object{id: int, title: string, has_spam_check: bool}|null
     */
    public function findEventAwaitingSpamCheck(CrmAutomationDomainObject $automation, CarbonInterface $settledBefore): ?object;

    /**
     * @return Collection<int, object{id: int, event_id: int, start_date: string}>
     */
    public function findFinishedOccurrences(CrmAutomationDomainObject $automation, CarbonInterface $now): Collection;

    public function findNextEndingOccurrence(int $organizerId, CarbonInterface $now): ?object;

    /**
     * @return Collection<int, object{event_id: int, contact_id: int}>
     */
    public function findAbandonedCheckoutContacts(CrmAutomationDomainObject $automation, CarbonInterface $abandonedFrom, CarbonInterface $abandonedUntil): Collection;

    /**
     * @return Collection<int, object{id: int, event_id: int, start_date: string}>
     */
    public function findOccurrencesDueAReminder(CrmAutomationDomainObject $automation, int $daysBefore, int $sendHour, CarbonInterface $notSentBefore, CarbonInterface $now): Collection;

    public function findUpcomingOccurrences(int $organizerId, CarbonInterface $now): Collection;

    public function replaceMembers(int $automationId, int $organizerId, CompiledSegmentDTO $segment): int;

    /**
     * @return int[]
     */
    public function findEntrants(int $automationId, int $organizerId, CompiledSegmentDTO $segment, int $limit): array;

    public function addMembersFromLatestLaunchedCampaign(int $automationId, CarbonInterface $launchedSince): void;

    /**
     * @param  int[]  $contactIds
     */
    public function addMembers(int $automationId, array $contactIds): void;

    public function clearMembers(int $automationId): void;
}
