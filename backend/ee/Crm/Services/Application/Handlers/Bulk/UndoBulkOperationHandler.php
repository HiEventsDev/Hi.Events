<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk;

use HiEvents\DomainObjects\CrmBulkOperationDomainObject;
use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\DomainObjects\Generated\CrmBulkOperationDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmBulkOperationStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Bulk\BulkOperationProcessor;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Support\Carbon;

class UndoBulkOperationHandler
{
    public const UNDO_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly CrmBulkOperationRepositoryInterface $bulkOperationRepository,
        private readonly BulkOperationProcessor $processor,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(int $organizerId, int $operationId, int $userId): CrmBulkOperationDomainObject
    {
        $operation = $this->bulkOperationRepository->findFirstWhere([
            CrmBulkOperationDomainObjectAbstract::ID => $operationId,
            CrmBulkOperationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]) ?? throw new ResourceNotFoundException(__('Bulk action not found'));

        $isUndoable = CrmBulkAction::from($operation->getAction())->isTagAction()
            && $operation->getStatus() === CrmBulkOperationStatus::COMPLETED->value
            && $operation->getUserId() === $userId
            && $operation->getFinishedAt() !== null
            && Carbon::parse($operation->getFinishedAt())->gt(now()->subMinutes(self::UNDO_WINDOW_MINUTES));

        if (! $isUndoable) {
            throw new ResourceConflictException(__('This change can no longer be undone.'));
        }

        $this->processor->undoTagChange($operation);

        $this->bulkOperationRepository->updateWhere(
            attributes: [
                CrmBulkOperationDomainObjectAbstract::STATUS => CrmBulkOperationStatus::UNDONE->value,
            ],
            where: [
                CrmBulkOperationDomainObjectAbstract::ID => $operation->getId(),
                CrmBulkOperationDomainObjectAbstract::STATUS => CrmBulkOperationStatus::COMPLETED->value,
            ],
        );

        return $this->bulkOperationRepository->findById($operation->getId());
    }
}
