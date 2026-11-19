<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Bulk;

use HiEvents\DomainObjects\CrmBulkOperationDomainObject;
use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\Enterprise\Crm\Jobs\ProcessBulkOperationChunkJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use Illuminate\Database\DatabaseManager;

class BulkOperationProcessor
{
    public const CHUNK_SIZE = 500;

    public function __construct(
        private readonly CrmBulkOperationRepositoryInterface $bulkOperationRepository,
        private readonly ConsentRecorder $consentRecorder,
        private readonly DeletedContactService $deletedContactService,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function start(CrmBulkOperationDomainObject $operation): void
    {
        if ($operation->getTotal() <= self::CHUNK_SIZE) {
            $this->processNextChunk($operation, 0);

            return;
        }

        ProcessBulkOperationChunkJob::dispatch($operation->getId(), 0);
    }

    /**
     * @return int|null the last contact id claimed when contacts remain, null once nothing is left
     */
    public function processNextChunk(CrmBulkOperationDomainObject $operation, int $afterContactId): ?int
    {
        $organizerId = $operation->getOrganizerId();

        $contactIds = $this->databaseManager->transaction(function () use ($operation, $organizerId, $afterContactId): array {
            $this->bulkOperationRepository->lockOrganizer($organizerId);

            $contactIds = $this->bulkOperationRepository->claimNext($operation->getId(), $afterContactId, self::CHUNK_SIZE);

            $this->bulkOperationRepository->recordProgress(
                $operation->getId(),
                count($contactIds),
                $this->apply($operation, $contactIds),
            );

            return $contactIds;
        });

        $completed = $this->bulkOperationRepository->completeIfFinished($operation->getId());
        $this->cacheVersion->bumpAfterCommit($organizerId);

        return $completed || $contactIds === [] ? null : max($contactIds);
    }

    public function undoTagChange(CrmBulkOperationDomainObject $operation): void
    {
        $organizerId = $operation->getOrganizerId();
        $tagIds = $operation->getTagIds() ?? [];
        $addedTags = CrmBulkAction::from($operation->getAction()) === CrmBulkAction::ADD_TAGS;

        do {
            $reversed = $this->databaseManager->transaction(function () use ($operation, $organizerId, $tagIds, $addedTags): int {
                $this->bulkOperationRepository->lockOrganizer($organizerId);

                $contactIds = $this->bulkOperationRepository->claimNextChanged($operation->getId(), self::CHUNK_SIZE);

                if ($addedTags) {
                    $this->bulkOperationRepository->removeTags($organizerId, $contactIds, $tagIds);
                } else {
                    $this->bulkOperationRepository->addTags($organizerId, $contactIds, $tagIds);
                }

                return count($contactIds);
            });
        } while ($reversed === self::CHUNK_SIZE);

        $this->cacheVersion->bumpAfterCommit($organizerId);
    }

    /**
     * @param  int[]  $contactIds
     * @return int[] the contacts that changed
     */
    private function apply(CrmBulkOperationDomainObject $operation, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        $organizerId = $operation->getOrganizerId();
        $tagIds = $operation->getTagIds() ?? [];

        return match (CrmBulkAction::from($operation->getAction())) {
            CrmBulkAction::ADD_TAGS => $this->bulkOperationRepository->addTags($organizerId, $contactIds, $tagIds),
            CrmBulkAction::REMOVE_TAGS => $this->bulkOperationRepository->removeTags($organizerId, $contactIds, $tagIds),
            CrmBulkAction::UNSUBSCRIBE => $this->consentRecorder->recordOptOuts($organizerId, $contactIds, $operation->getUserId()),
            CrmBulkAction::DELETE => $this->deletedContactService->delete($organizerId, $contactIds),
        };
    }
}
