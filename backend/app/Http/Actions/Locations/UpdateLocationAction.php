<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Location\UpsertLocationRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Location\LocationResource;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Application\Handlers\Location\UpdateLocationHandler;
use Illuminate\Http\JsonResponse;

class UpdateLocationAction extends BaseAction
{
    public function __construct(
        private readonly UpdateLocationHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $locationId, UpsertLocationRequest $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');

        try {
            $location = $this->handler->handle($locationId, new UpsertLocationDTO(
                organizer_id: $organizerId,
                account_id: $this->getAuthenticatedAccountId(),
                name: $request->validated('name'),
                structured_address: AddressDTO::from($request->validated('structured_address')),
                latitude: $latitude === null ? null : (float) $latitude,
                longitude: $longitude === null ? null : (float) $longitude,
                provider: $request->validated('provider'),
                provider_place_id: $request->validated('provider_place_id'),
            ));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: ResponseCodes::HTTP_CONFLICT,
            );
        }

        return $this->resourceResponse(
            resource: LocationResource::class,
            data: $location,
        );
    }
}
