<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Http\Request\UpsertBoxOfficeRequest;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\UpsertBoxOfficeDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\UpdateBoxOfficeHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UpdateBoxOfficeAction extends BaseBoxOfficeAction
{
    public function __construct(
        private readonly UpdateBoxOfficeHandler $updateBoxOfficeHandler,
    ) {}

    public function __invoke(UpsertBoxOfficeRequest $request, int $eventId, int $boxOfficeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->assertBoxOfficeEnabled();

        try {
            $boxOffice = $this->updateBoxOfficeHandler->handle(
                new UpsertBoxOfficeDTO(
                    name: $request->validated('name'),
                    description: $request->validated('description'),
                    event_id: $eventId,
                    product_ids: $request->validated('product_ids') ?? [],
                    event_occurrence_id: $request->validated('event_occurrence_id'),
                    check_in_list_id: $request->validated('check_in_list_id'),
                    allow_price_override: $request->validated('allow_price_override') ?? false,
                    allow_discounts: $request->validated('allow_discounts') ?? true,
                    collect_order_questions: $request->validated('collect_order_questions') ?? false,
                    activates_at: $request->validated('activates_at'),
                    expires_at: $request->validated('expires_at'),
                    id: $boxOfficeId,
                )
            );
        } catch (UnrecognizedProductIdException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->resourceResponse(
            resource: BoxOfficeResource::class,
            data: $boxOffice,
        );
    }
}
