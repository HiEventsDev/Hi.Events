<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Segments;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertSegmentRequest;
use HiEvents\Enterprise\Crm\Resources\SegmentResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\DTO\UpsertSegmentDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Segment\UpsertSegmentHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateSegmentAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertSegmentHandler $handler,
    ) {}

    public function __invoke(UpsertSegmentRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $segment = $this->handler->handle(new UpsertSegmentDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                name: $request->validated('name'),
                description: $request->validated('description'),
                definition: $request->validated('definition'),
                can_view_financials: $this->canViewFinancials(),
                segment_id: null,
            ));
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(SegmentResource::class, $segment, Response::HTTP_CREATED);
    }
}
