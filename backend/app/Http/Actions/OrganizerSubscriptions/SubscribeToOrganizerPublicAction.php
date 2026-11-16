<?php

namespace HiEvents\Http\Actions\OrganizerSubscriptions;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\OrganizerSubscription\SubscribeToOrganizerRequest;
use HiEvents\Services\Application\Handlers\OrganizerSubscription\SubscribeToOrganizerHandler;
use Illuminate\Http\Response;

class SubscribeToOrganizerPublicAction extends BaseAction
{
    public function __construct(private readonly SubscribeToOrganizerHandler $handler) {}

    public function __invoke(SubscribeToOrganizerRequest $request, int $organizerId): Response
    {
        $this->handler->handle($organizerId, $request->validated('email'), $request->validated('locale', config('app.locale')));

        return $this->noContentResponse();
    }
}
