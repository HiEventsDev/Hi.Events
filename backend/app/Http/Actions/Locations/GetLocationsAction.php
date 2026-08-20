<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Locations;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\Location\LocationResource;
use HiEvents\Services\Application\Handlers\Location\GetLocationsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetLocationsAction extends BaseAction
{
    public function __construct(
        private readonly GetLocationsHandler $handler,
    ) {}

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $locations = $this->handler->handle(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            params: QueryParamsDTO::fromArray($request->query()),
        );

        return $this->filterableResourceResponse(
            resource: LocationResource::class,
            data: $locations,
            domainObject: LocationDomainObject::class,
        );
    }
}
