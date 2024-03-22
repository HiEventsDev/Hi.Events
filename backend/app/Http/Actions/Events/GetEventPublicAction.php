<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Services\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Handlers\Event\GetPublicEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetEventPublicAction extends BaseAction
{
    private GetPublicEventHandler $handler;

    public function __construct(GetPublicEventHandler $handler)
    {
        $this->handler = $handler;
    }

    public function __invoke(int $eventId, Request $request): Response|JsonResponse
    {
        $event = $this->handler->handle(GetPublicEventDTO::fromArray([
            'eventId' => $eventId,
            'ipAddress' => $this->getClientIp($request),
            'promoCode' => strtolower($request->string('promo_code')),
            'isAuthenticated' => $this->isUserAuthenticated(),
        ]));

        if ($event->getStatus() !== EventStatus::LIVE->name && !$this->isUserAuthenticated()) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(EventResourcePublic::class, $event);
    }
}
