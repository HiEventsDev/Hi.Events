<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Import;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\DomainObjects\Enums\CrmImportKind;
use HiEvents\DomainObjects\Generated\CrmImportDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmImportStatus;
use HiEvents\Enterprise\Crm\Jobs\ProcessContactImportChunkJob;
use HiEvents\Enterprise\Crm\Repository\DTO\ImportChunkResultDTO;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmImportRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ContactImportRunDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ImportSkippedReasonsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ParsedContactImportDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

class ContactImportRunner
{
    public const CHUNK_SIZE = 500;

    public function __construct(
        private readonly CrmImportRepositoryInterface $importRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly DeletedContactHasher $deletedContactHasher,
        private readonly DatabaseManager $databaseManager,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    /**
     * @throws Throwable
     */
    public function start(ContactImportRunDTO $run, ParsedContactImportDTO $parsed): CrmImportDomainObject
    {
        $rows = array_values($parsed->rows);
        $duplicates = $parsed->total_rows - $parsed->invalid_rows - count($rows);

        $import = $this->databaseManager->transaction(function () use ($run, $parsed, $rows, $duplicates): CrmImportDomainObject {
            $import = $this->importRepository->create([
                CrmImportDomainObjectAbstract::ACCOUNT_ID => $run->account_id,
                CrmImportDomainObjectAbstract::ORGANIZER_ID => $run->organizer_id,
                CrmImportDomainObjectAbstract::USER_ID => $run->user_id,
                CrmImportDomainObjectAbstract::FILE_NAME => mb_substr($run->file_name, 0, 255),
                CrmImportDomainObjectAbstract::STATUS => CrmImportStatus::PROCESSING->value,
                CrmImportDomainObjectAbstract::KIND => $run->kind->value,
                CrmImportDomainObjectAbstract::CONSENT_ATTESTED => $run->consent_attested,
                CrmImportDomainObjectAbstract::TOTAL_ROWS => $parsed->total_rows,
                CrmImportDomainObjectAbstract::SKIPPED_COUNT => $parsed->invalid_rows + $duplicates,
                CrmImportDomainObjectAbstract::SKIPPED_REASONS => (new ImportSkippedReasonsDTO(
                    invalid_email: $parsed->invalid_rows,
                    duplicate_in_file: $duplicates,
                    previously_deleted: 0,
                    already_suppressed: 0,
                ))->toArray(),
            ]);

            $this->importRepository->addRows($import->getId(), $rows);

            return $import;
        });

        try {
            if (count($rows) > self::CHUNK_SIZE) {
                ProcessContactImportChunkJob::dispatch($import->getId(), $run->tag_id);
            } else {
                $this->processNextChunk($import->getId(), $run->tag_id);
            }
        } catch (Throwable $exception) {
            $this->importRepository->markFailed($import->getId());

            throw $exception;
        }

        return $this->importRepository->findById($import->getId());
    }

    /**
     * @return bool whether rows are left for another chunk
     */
    public function processNextChunk(int $importId, ?int $tagId): bool
    {
        $organizerId = $this->databaseManager->transaction(function () use ($importId, $tagId): ?int {
            $import = $this->importRepository->lockIfProcessing($importId);

            if ($import === null) {
                return null;
            }

            $rows = $this->importRepository->claimRows($importId, self::CHUNK_SIZE);

            if ($rows !== []) {
                $result = $this->importRows($import, $rows, $tagId);
                $this->projectionRepository->refreshContacts($import->getOrganizerId(), $result->contact_ids);
                $this->importRepository->recordProgress($importId, $result);
            }

            return $import->getOrganizerId();
        });

        if ($organizerId === null) {
            return false;
        }

        $this->cacheVersion->bumpAfterCommit($organizerId);

        return ! $this->importRepository->completeIfFinished($importId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importRows(CrmImportDomainObject $import, array $rows, ?int $tagId): ImportChunkResultDTO
    {
        $salt = $this->deletedContactHasher->salt($import->getOrganizerId());

        if ($import->getKind() === CrmImportKind::SUPPRESSIONS->value) {
            return $this->contactRepository->importSuppressions(
                accountId: $import->getAccountId(),
                organizerId: $import->getOrganizerId(),
                importId: $import->getId(),
                userId: $import->getUserId(),
                rows: $rows,
                deletedContactSalt: $salt,
            );
        }

        return $this->contactRepository->importContacts(
            accountId: $import->getAccountId(),
            organizerId: $import->getOrganizerId(),
            importId: $import->getId(),
            userId: $import->getUserId(),
            rows: $rows,
            consentAttested: $import->getConsentAttested(),
            tagId: $tagId,
            deletedContactSalt: $salt,
        );
    }
}
