<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk;

use HiEvents\DomainObjects\CrmBulkOperationDomainObject;
use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\DomainObjects\Generated\CrmBulkOperationDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmBulkOperationStatus;
use HiEvents\Enterprise\Crm\Exceptions\BulkSelectionChangedException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Exceptions\UnknownTagException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmTagRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\DTO\CreateBulkOperationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Bulk\BulkOperationProcessor;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateBulkOperationHandler
{
    public function __construct(
        private readonly CrmBulkOperationRepositoryInterface $bulkOperationRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmTagRepositoryInterface $tagRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly BulkOperationProcessor $processor,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws UnknownTagException
     * @throws ResourceConflictException
     * @throws BulkSelectionChangedException
     * @throws InvalidSegmentDefinitionException
     * @throws Throwable
     */
    public function handle(CreateBulkOperationDTO $dto): CrmBulkOperationDomainObject
    {
        $tagIds = array_values(array_unique($dto->tag_ids));

        if ($dto->action->isTagAction() && ($tagIds === [] || $this->tagRepository->countForOrganizer($dto->organizer_id, $tagIds) !== count($tagIds))) {
            throw new UnknownTagException(__('Tag not found'));
        }

        $isConfirmed = in_array($dto->action, [CrmBulkAction::UNSUBSCRIBE, CrmBulkAction::DELETE], true);

        $segment = $dto->contact_ids === null
            ? $this->segmentResolver->resolve($dto->organizer_id, $dto->segment, $dto->can_view_financials)
            : null;

        $operation = $this->databaseManager->transaction(function () use ($dto, $tagIds, $segment, $isConfirmed): CrmBulkOperationDomainObject {
            if ($isConfirmed) {
                $this->bulkOperationRepository->lockOrganizer($dto->organizer_id);

                if ($this->bulkOperationRepository->hasRunningDestructiveOperation($dto->organizer_id)) {
                    throw new ResourceConflictException(__('Another unsubscribe or delete is still running. Try again when it finishes.'));
                }
            }

            $operation = $this->bulkOperationRepository->create([
                CrmBulkOperationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                CrmBulkOperationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                CrmBulkOperationDomainObjectAbstract::USER_ID => $dto->user_id,
                CrmBulkOperationDomainObjectAbstract::ACTION => $dto->action->value,
                CrmBulkOperationDomainObjectAbstract::TAG_IDS => $dto->action->isTagAction() ? $tagIds : null,
                CrmBulkOperationDomainObjectAbstract::STATUS => CrmBulkOperationStatus::RUNNING->value,
            ]);

            $total = $this->contactRepository->snapshotForBulkOperation(
                operationId: $operation->getId(),
                organizerId: $dto->organizer_id,
                contactIds: $dto->contact_ids,
                query: $dto->contact_ids === null ? $dto->query : null,
                segment: $segment,
            );

            if ($isConfirmed && $dto->confirmation_count !== $total) {
                throw new BulkSelectionChangedException($total);
            }

            return $this->bulkOperationRepository->updateFromArray($operation->getId(), [
                CrmBulkOperationDomainObjectAbstract::TOTAL => $total,
            ]);
        });

        try {
            $this->processor->start($operation);
        } catch (Throwable $exception) {
            $this->bulkOperationRepository->markFailed($operation->getId());

            throw $exception;
        }

        return $this->bulkOperationRepository->findById($operation->getId());
    }
}
