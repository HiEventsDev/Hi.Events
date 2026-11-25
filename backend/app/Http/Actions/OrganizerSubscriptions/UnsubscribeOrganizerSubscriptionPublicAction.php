<?php

namespace HiEvents\Http\Actions\OrganizerSubscriptions;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\OrganizerSubscription\ManageOrganizerSubscriptionsHandler;
use Illuminate\Http\Response;

class UnsubscribeOrganizerSubscriptionPublicAction extends BaseAction
{
    public function __construct(private readonly ManageOrganizerSubscriptionsHandler $handler) {}

    public function __invoke(int $organizerId, string $token): Response
    {
        $this->handler->unsubscribe($organizerId, $token);

        return $this->noContentResponse();
    }
}
