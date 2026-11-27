<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmCampaignSource;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmCampaignDomainObject>
 */
interface CrmCampaignRepositoryInterface extends RepositoryInterface
{
    public function findByOrganizerId(int $organizerId, ?string $folder, ?CrmCampaignSource $source, ?string $search, int $page, int $perPage): LengthAwarePaginator;

    public function countManualStartedSince(int $accountId, CarbonInterface $since): int;

    public function lockAccountLaunches(int $accountId): void;

    public function hasSentCampaign(int $accountId): bool;

    /**
     * @return int[]
     */
    public function findDueScheduledIds(CarbonInterface $now, int $limit): array;

    /**
     * @return Collection<int, CrmCampaignDomainObject>
     */
    public function findStaleReviews(CarbonInterface $scheduledBefore, CarbonInterface $automationLaunchedBefore, int $limit): Collection;

    public function findForLaunch(int $campaignId): ?CrmCampaignDomainObject;

    public function transitionStatus(int $campaignId, array $fromStatuses, array $attributes, ?CarbonInterface $onlyIfScheduledBy = null, ?CarbonInterface $onlyIfLastUpdatedAt = null): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateIfEditable(int $campaignId, array $attributes): bool;

    public function touchIfStatus(int $campaignId, CrmCampaignStatus $status): bool;

    /**
     * @param  array{decision: string, confidence: float, reasons: string[], model: string, reviewed_at: string}  $verdict
     */
    public function recordAiReview(int $campaignId, array $verdict, CarbonInterface $reviewedVersion): bool;

    public function snapshotRecipients(int $campaignId, int $organizerId, ?CompiledSegmentDTO $segment, CarbonInterface $now): int;

    /**
     * @return int[]
     */
    /**
     * @param  string[]  $countries
     */
    public function snapshotSmsRecipients(int $campaignId, int $organizerId, ?CompiledSegmentDTO $segment, CarbonInterface $now, array $countries): int;

    public function recordSmsDeliveryStatus(int $organizerId, string $providerMessageId, bool $delivered, CarbonInterface $now): ?int;

    /**
     * @return string[]
     */
    public function findPendingRecipientCountries(int $campaignId): array;

    /**
     * @param  int[]  $campaignIds
     * @return Collection<int, object{campaign_id: int, phone_country: string, pending_count: int}>
     */
    public function countPendingRecipientsByCountry(array $campaignIds): Collection;

    public function findPendingRecipientIds(int $campaignId, int $afterRecipientId, int $limit, ?array $countries = null): array;

    public function findRecipient(int $recipientId): ?object;

    public function markRecipientSent(int $recipientId, ?string $providerMessageId, CarbonInterface $now): void;

    public function markRecipientSkipped(int $recipientId, CrmRecipientFailureReason $reason): bool;

    public function markRecipientFailed(int $recipientId, CrmRecipientFailureReason $reason): bool;

    public function skipPendingRecipients(int $campaignId): void;

    public function recordCampaignActivity(int $recipientId, CarbonInterface $now): void;

    public function hasPendingRecipients(int $campaignId): bool;

    /**
     * @param  string[]|null  $countries
     */
    public function countPendingRecipientsBetween(int $campaignId, int $afterRecipientId, int $upToRecipientId, ?array $countries = null): int;

    public function unclaimRecipient(int $recipientId): void;

    public function claimRecipient(int $recipientId): bool;

    public function failStalledRecipients(CarbonInterface $stalledBefore): void;

    /**
     * @return int[]
     */
    public function findStalledCampaignIds(CarbonInterface $stalledBefore, int $limit): array;

    public function hasRecipients(int $campaignId): bool;

    public function withdrawRecipients(int $campaignId, bool $keepAsSkipped = false): void;

    public function findRecipients(int $campaignId, ?string $filter, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return array<string, int>
     */
    public function countByFolder(int $organizerId): array;

    /**
     * @param  int[]  $campaignIds
     * @return Collection<int, object>
     */
    public function getDeliveryStats(array $campaignIds): Collection;

    /**
     * @return object{sent: int, complained: int, bounced: int}
     */
    public function getHealthStats(int $campaignId): object;

    /**
     * @return Collection<int, object>
     */
    public function getAttributedRevenue(CrmCampaignDomainObject $campaign): Collection;

    /**
     * @return Collection<int, object>
     */
    public function findUnrecordedFeedback(CarbonInterface $recordedSince, int $limit): Collection;

    /**
     * @return Collection<int, CrmCampaignDomainObject>
     */
    public function findPendingReview(): Collection;

    /**
     * @param  int[]  $campaignIds
     * @return array<int, int> recipients whose only opt-in is an import, keyed by campaign id
     */
    public function countImportOnlyRecipients(array $campaignIds): array;

    /**
     * @param  int[]  $contactIds
     */
    public function addExplicitAudience(int $campaignId, array $contactIds): void;

    public function hasBoughtSince(int $organizerId, int $contactId, int $eventId, string $since): bool;

    public function findEventCard(int $eventId, ?int $occurrenceId, CarbonInterface $now): ?object;

    public function findLastAttendedEvent(int $contactId): ?object;

    public function findCurrentOrNextOccurrenceStart(int $eventId, CarbonInterface $now): ?string;

    public function hasCurrentOrUpcomingDate(int $eventId, CarbonInterface $now): bool;
}
