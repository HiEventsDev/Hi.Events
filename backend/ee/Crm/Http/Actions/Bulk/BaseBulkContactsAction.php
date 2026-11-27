<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Bulk;

use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\Enterprise\Crm\Exceptions\BulkSelectionChangedException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Exceptions\UnknownTagException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\BulkContactsRequest;
use HiEvents\Enterprise\Crm\Resources\BulkOperationResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\CreateBulkOperationHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\DTO\CreateBulkOperationDTO;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

abstract class BaseBulkContactsAction extends BaseCrmAction
{
    public function __construct(
        private readonly CreateBulkOperationHandler $handler,
    ) {}

    /**
     * @param  int[]  $tagIds
     */
    protected function startBulkOperation(BulkContactsRequest $request, int $organizerId, CrmBulkAction $action, array $tagIds = []): JsonResponse
    {
        try {
            $operation = $this->handler->handle(new CreateBulkOperationDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                user_id: $this->getAuthenticatedUser()->getId(),
                action: $action,
                contact_ids: $request->contactIds(),
                query: $request->validated('query'),
                segment: $this->segmentReference($request),
                can_view_financials: $this->canViewFinancials(),
                tag_ids: $tagIds,
                confirmation_count: $request->validated('confirmation_count') === null ? null : (int) $request->validated('confirmation_count'),
            ));
        } catch (BulkSelectionChangedException $exception) {
            return $this->jsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => 'SELECTION_CHANGED',
                'total' => $exception->total,
            ], Response::HTTP_CONFLICT);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (UnknownTagException $exception) {
            throw ValidationException::withMessages(['tag_ids' => $exception->getMessage()]);
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        }

        return $this->resourceResponse(BulkOperationResource::class, $operation, Response::HTTP_CREATED);
    }
}
