<?php

namespace TicketKitten\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use TicketKitten\DomainObjects\Status\EventStatus;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Resources\Event\EventResourcePublic;
use TicketKitten\Service\Handler\Event\GetPublicEventHandler;

class GetEventPublicAction extends BaseAction
{
    private GetPublicEventHandler $handler;

    public function __construct(GetPublicEventHandler $handler)
    {
        $this->handler = $handler;
    }

    public function __invoke(int $eventId, Request $request): Response|JsonResponse
    {
        $event = $this->handler->handle($eventId, strtolower($request->string('promo_code')));

        if ($event->getStatus() !== EventStatus::LIVE->name && !$this->isUserAuthenticated()) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(EventResourcePublic::class, $event);
    }
}
