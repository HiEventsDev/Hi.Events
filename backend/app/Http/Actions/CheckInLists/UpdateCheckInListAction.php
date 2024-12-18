<?php

namespace HiEvents\Http\Actions\CheckInLists;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CheckInList\UpsertCheckInListRequest;
use HiEvents\Resources\CheckInList\CheckInListResource;
use HiEvents\Services\Application\Handlers\CheckInList\DTO\UpsertCheckInListDTO;
use HiEvents\Services\Application\Handlers\CheckInList\UpdateCheckInlistHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UpdateCheckInListAction extends BaseAction
{
    public function __construct(
        private readonly UpdateCheckInlistHandler $updateCheckInlistHandler,
    )
    {
    }

    public function __invoke(UpsertCheckInListRequest $request, int $eventId, int $checkInListId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $checkInList = $this->updateCheckInlistHandler->handle(
                new UpsertCheckInListDTO(
                    name: $request->validated('name'),
                    description: $request->validated('description'),
                    eventId: $eventId,
                    productIds: $request->validated('product_ids'),
                    expiresAt: $request->validated('expires_at'),
                    activatesAt: $request->validated('activates_at'),
                    id: $checkInListId,
                )
            );
        } catch (UnrecognizedProductIdException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->resourceResponse(
            resource: CheckInListResource::class,
            data: $checkInList
        );
    }
}
