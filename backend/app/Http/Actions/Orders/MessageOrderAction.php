<?php

namespace HiEvents\Http\Actions\Orders;

use Illuminate\Http\Response;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Message\SendMessageRequest;
use HiEvents\Jobs\SendMessagesJob;

class MessageOrderAction extends BaseAction
{
    public function __invoke(SendMessageRequest $request, int $eventId, int $orderId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        SendMessagesJob::dispatch($orderId, $request->input('subject'), $request->input('message'));

        return $this->noContentResponse();
    }
}
