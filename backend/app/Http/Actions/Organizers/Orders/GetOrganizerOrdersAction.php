<?php

namespace HiEvents\Http\Actions\Organizers\Orders;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Application\Handlers\Organizer\Order\GetOrganizerOrdersHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrganizerOrdersAction extends BaseAction
{
    public function __construct(
        private readonly GetOrganizerOrdersHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $orders = $this->handler->handle(
            organizer: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            queryParams: $this->getPaginationQueryParams($request)
        );

        return $this->filterableResourceResponse(
            resource: OrderResource::class,
            data: $orders,
            domainObject: OrderDomainObject::class
        );
    }
}
