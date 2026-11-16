<?php

namespace HiEvents\Http\Actions\OrganizerSubscriptions;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\OrganizerSubscription\GetOrganizerSubscriptionsRequest;
use HiEvents\Resources\Organizer\OrganizerEventSubscriptionResource;
use HiEvents\Services\Application\Handlers\OrganizerSubscription\ManageOrganizerSubscriptionsHandler;
use Illuminate\Http\JsonResponse;

class GetOrganizerSubscribersAction extends BaseAction
{
    public function __construct(private readonly ManageOrganizerSubscriptionsHandler $handler) {}

    public function __invoke(GetOrganizerSubscriptionsRequest $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->resourceResponse(OrganizerEventSubscriptionResource::class,
            $this->handler->subscribers($organizerId, $request->validated('query'), $request->validated('status'), $request->integer('per_page', 25), $request->integer('page', 1)),
            meta: ['counts' => $this->handler->counts($organizerId)->toArray()],
        );
    }
}
