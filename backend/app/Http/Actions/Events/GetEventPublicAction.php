<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;

class GetEventPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetPublicEventHandler $getPublicEventHandler,
        private readonly LoggerInterface       $logger,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): Response|JsonResponse
    {
        $event = $this->getPublicEventHandler->handle(GetPublicEventDTO::fromArray([
            'eventId' => $eventId,
            'ipAddress' => $this->getClientIp($request),
            'promoCode' => strtolower($request->string('promo_code')),
            'isAuthenticated' => $this->isUserAuthenticated(),
        ]));

        if (!$this->canUserViewEvent($event)) {
            $this->logger->debug(__('Event with ID :eventId is not live and user is not authenticated', [
                'eventId' => $eventId
            ]));

            return $this->notFoundResponse();
        }

        return $this->resourceResponse(EventResourcePublic::class, $event);
    }

    private function canUserViewEvent(EventDomainObject $event): bool
    {
        if ($event->getStatus() === EventStatus::LIVE->name) {
            return true;
        }

        if ($this->isUserAuthenticated() && $event->getAccountId() === $this->getAuthenticatedAccountId()) {
            return true;
        }

        return false;
    }
}
