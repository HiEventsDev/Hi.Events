<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Bulk;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\BulkOperationResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\UndoBulkOperationHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UndoBulkOperationAction extends BaseCrmAction
{
    public function __construct(
        private readonly UndoBulkOperationHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $operationId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $operation = $this->handler->handle($organizerId, $operationId, $this->getAuthenticatedUser()->getId());
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(BulkOperationResource::class, $operation);
    }
}
