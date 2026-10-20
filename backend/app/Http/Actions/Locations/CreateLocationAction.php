<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Location\UpsertLocationRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Location\LocationResource;
use HiEvents\Services\Application\Handlers\Location\CreateLocationHandler;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use Illuminate\Http\JsonResponse;

class CreateLocationAction extends BaseAction
{
    public function __construct(
        private readonly CreateLocationHandler $handler,
    ) {}

    public function __invoke(int $organizerId, UpsertLocationRequest $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');

        $location = $this->handler->handle(new UpsertLocationDTO(
            organizer_id: $organizerId,
            account_id: $this->getAuthenticatedAccountId(),
            name: $request->validated('name'),
            structured_address: AddressDTO::from($request->validated('structured_address')),
            latitude: $latitude === null ? null : (float) $latitude,
            longitude: $longitude === null ? null : (float) $longitude,
            provider: $request->validated('provider'),
            provider_place_id: $request->validated('provider_place_id'),
        ));

        return $this->resourceResponse(
            resource: LocationResource::class,
            data: $location,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
