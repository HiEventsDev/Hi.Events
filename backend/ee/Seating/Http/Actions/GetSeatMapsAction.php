<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Enterprise\Seating\Resources\SeatMapPreviewResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetSeatMapsHandler;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetSeatMapsAction extends BaseSeatMapAction
{
    public function __construct(
        private readonly GetSeatMapsHandler $handler,
    ) {}

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->authorizeSeatMapAccess($organizerId);

        $seatMaps = $this->handler->handle(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            params: QueryParamsDTO::fromArray($request->query()),
        );

        return $this->filterableResourceResponse(
            resource: SeatMapPreviewResource::class,
            data: $seatMaps,
            domainObject: SeatMapDomainObject::class,
        );
    }
}
