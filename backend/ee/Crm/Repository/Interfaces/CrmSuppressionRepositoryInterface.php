<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\DTO\SuppressionDetailDTO;
use HiEvents\Enterprise\Crm\Repository\DTO\SuppressionImportMatchDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmContactDomainObject>
 */
interface CrmSuppressionRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]  $contactIds
     * @return Collection<int, SuppressionDetailDTO>
     */
    public function findDetailsForContacts(int $organizerId, array $contactIds): Collection;

    /**
     * @return array<string, int>
     */
    public function countByReason(int $organizerId): array;

    public function countSuppressedTombstones(int $organizerId): int;

    /**
     * @param  string[]  $emails
     * @return Collection<string, CrmContactDomainObject>
     */
    public function findContactsByEmail(int $organizerId, array $emails): Collection;

    /**
     * @param  string[]  $emails
     * @return array<string, string|null>
     */
    public function findTombstoneSuppressions(int $organizerId, array $emails, string $salt): array;

    /**
     * @param  string[]  $emails
     * @param  string[]  $bouncedEmails  the addresses in $emails listed as bounced, which only suppress a contact when they are its primary address
     */
    public function countMatchingContacts(int $organizerId, array $emails, array $bouncedEmails): SuppressionImportMatchDTO;
}
