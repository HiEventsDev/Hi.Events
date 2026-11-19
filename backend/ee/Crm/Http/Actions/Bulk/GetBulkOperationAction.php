<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Bulk;

use HiEvents\DomainObjects\Generated\CrmBulkOperationDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmBulkOperationRepositoryInterface;
use HiEvents\Enterprise\Crm\Resources\BulkOperationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetBulkOperationAction extends BaseCrmAction
{
    public function __construct(
        private readonly CrmBulkOperationRepositoryInterface $bulkOperationRepository,
    ) {}

    public function __invoke(int $organizerId, int $operationId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $this->bulkOperationRepository->failStalled($organizerId);

        $operation = $this->bulkOperationRepository->findFirstWhere([
            CrmBulkOperationDomainObjectAbstract::ID => $operationId,
            CrmBulkOperationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($operation === null) {
            return $this->errorResponse(__('Bulk action not found'), Response::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(BulkOperationResource::class, $operation);
    }
}
