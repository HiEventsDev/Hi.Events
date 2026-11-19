<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\DTO\ImportChunkResultDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmImportDomainObject>
 */
interface CrmImportRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, CrmImportDomainObject>
     */
    public function findRecentForOrganizer(int $organizerId, int $limit): Collection;

    /**
     * @param  array<int, array{email: string, first_name: string|null, last_name: string|null, reason: CrmEmailMarketingStatus}>  $rows
     */
    public function addRows(int $importId, array $rows): void;

    public function lockIfProcessing(int $importId): ?CrmImportDomainObject;

    /**
     * @return array<int, array{email: string, first_name: string|null, last_name: string|null, reason: CrmEmailMarketingStatus}>
     */
    public function claimRows(int $importId, int $limit): array;

    public function recordProgress(int $importId, ImportChunkResultDTO $result): void;

    public function completeIfFinished(int $importId): bool;

    public function markFailed(int $importId): void;

    public function failStalled(?int $organizerId = null): void;
}
