<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\DTO\DeletedContactConsentDTO;
use HiEvents\Enterprise\Crm\Repository\DTO\ImportChunkResultDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmContactDomainObject>
 */
interface CrmContactRepositoryInterface extends RepositoryInterface
{
    public function findByOrganizerId(int $organizerId, QueryParamsDTO $params, ?CompiledSegmentDTO $segment): LengthAwarePaginator;

    /**
     * @return Collection<int, CrmContactDomainObject>
     */
    /**
     * @param  int[]|null  $contactIds
     */
    public function findForExport(int $organizerId, ?string $query, ?CompiledSegmentDTO $segment, ?string $afterEmail, int $limit, ?array $contactIds = null): Collection;

    public function findContactIdByEmail(int $organizerId, string $email): ?int;

    /**
     * @param  int[]  $contactIds
     * @return Collection<int, Collection<int, object>>
     */
    public function findTagsForContacts(array $contactIds): Collection;

    /**
     * @param  int[]  $contactIds
     * @return Collection<int, Collection<int, object>>
     */
    public function findSpendForContacts(array $contactIds): Collection;

    /**
     * @return Collection<int, object>
     */
    public function findEmails(int $contactId): Collection;

    public function findConsentHistory(int $contactId, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, object>
     */
    public function findObservedNames(int $contactId): Collection;

    public function countNoShows(int $contactId, CarbonInterface $now): int;

    public function createWithEmail(array $attributes): CrmContactDomainObject;

    /**
     * @param  int[]  $tagIds
     */
    public function findContactEmailId(int $organizerId, int $contactId, string $email): ?int;

    /**
     * @param  int[]  $tagIds
     */
    public function attachTags(int $organizerId, int $contactId, array $tagIds): void;

    public function syncTags(int $organizerId, int $contactId, array $tagIds): void;

    /**
     * @param  int[]|null  $contactIds
     */
    public function snapshotForBulkOperation(int $operationId, int $organizerId, ?array $contactIds, ?string $query, ?CompiledSegmentDTO $segment): int;

    /**
     * @param  int[]  $contactIds
     * @return int[] the contacts that were opted out
     */
    public function recordOptOuts(int $organizerId, array $contactIds, ?int $userId): array;

    /**
     * @param  array<int, array{email: string, first_name: string|null, last_name: string|null}>  $rows
     */
    public function importContacts(
        int $accountId,
        int $organizerId,
        int $importId,
        ?int $userId,
        array $rows,
        bool $consentAttested,
        ?int $tagId,
        string $deletedContactSalt,
    ): ImportChunkResultDTO;

    /**
     * @param  array<int, array{email: string, first_name: string|null, last_name: string|null, reason: CrmEmailMarketingStatus}>  $rows
     */
    public function importSuppressions(
        int $accountId,
        int $organizerId,
        int $importId,
        ?int $userId,
        array $rows,
        string $deletedContactSalt,
    ): ImportChunkResultDTO;

    public function forgetDeletedEmail(int $organizerId, string $emailHash): DeletedContactConsentDTO;

    public function hasDeletedEmail(int $organizerId, string $emailHash): bool;

    public function findOrCreateDeletedContactSalt(int $organizerId): string;

    public function wasLastOptedOutByLink(int $contactId, int $recipientId): bool;

    /**
     * @param  int[]  $contactIds
     * @return int[]
     */
    public function findExistingIds(int $organizerId, array $contactIds): array;

    public function hasUnreviewedImportOptIns(int $organizerId, ?CompiledSegmentDTO $segment): bool;

    public function recordRecipientConsent(
        int $organizerId,
        int $contactId,
        string $email,
        CrmConsentAction $action,
        CrmConsentSource $source,
        int $recipientId,
    ): void;

    /**
     * @return object{contacts_count: int, mailable_count: int, textable_count: int}
     */
    public function countSegment(int $organizerId, ?CompiledSegmentDTO $segment): object;

    /**
     * @param  string[]  $countries
     */
    public function countTextable(int $organizerId, ?CompiledSegmentDTO $segment, array $countries): int;

    /**
     * @param  string[]  $countries
     * @return array<string, int> distinct subscribed numbers per country
     */
    public function countTextableByCountry(int $organizerId, ?CompiledSegmentDTO $segment, array $countries): array;

    public function insertConsentEvent(
        int $organizerId,
        int $contactId,
        string $email,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId,
        ?int $userId,
    ): void;

    public function insertSmsConsentEvent(
        int $organizerId,
        int $contactId,
        string $email,
        string $phone,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId,
        ?int $userId,
    ): void;

    /**
     * @param  int[]  $contactIds
     * @return int[] the contacts that were deleted
     */
    public function deleteContacts(int $organizerId, array $contactIds, string $emailHashSalt): array;

    /**
     * @return bool false when either contact no longer belongs to the organizer
     */
    public function strengthenDeletedRecipientSuppression(int $organizerId, int $recipientId, CrmEmailMarketingStatus $suppression): void;

    public function markDeletedRecipientOptedOutOfTexts(int $organizerId, int $recipientId): void;

    /**
     * @return object{suppression: ?string, sms_opted_out: bool}|null
     */
    public function findDeletedRecipientTombstone(int $organizerId, int $recipientId): ?object;

    public function relinkDeletedRecipients(int $organizerId, string $email, string $emailHash): void;

    public function mergeContacts(int $organizerId, int $targetContactId, int $sourceContactId): bool;

    public function splitEmail(int $organizerId, int $contactId, int $contactEmailId): ?int;

    public function setPrimaryEmail(int $organizerId, int $contactId, int $contactEmailId): bool;
}
