<?php

namespace HiEvents\Http\Actions\OrganizerSubscriptions;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\OrganizerSubscription\UnsubscribeOrganizerSubscribersRequest;
use HiEvents\Services\Application\Handlers\OrganizerSubscription\ManageOrganizerSubscriptionsHandler;
use Illuminate\Http\Response;

class UnsubscribeOrganizerSubscribersAction extends BaseAction
{
    public function __construct(private readonly ManageOrganizerSubscriptionsHandler $handler) {}

    public function __invoke(UnsubscribeOrganizerSubscribersRequest $request, int $organizerId): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);
        $this->requirePermission(Permission::EDIT);
        $this->handler->unsubscribeIds($organizerId, $request->validated('ids'));

        return $this->noContentResponse();
    }
}
