<?php

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\Response;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\Request\Message\SendMessageRequest;
use TicketKitten\Jobs\SendMessagesJob;

class MessageOrderAction extends BaseAction
{
    public function __invoke(SendMessageRequest $request, int $eventId, int $orderId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        SendMessagesJob::dispatch($orderId, $request->input('subject'), $request->input('message'));

        return $this->noContentResponse();
    }
}
