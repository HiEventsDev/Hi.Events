<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions\Public;

use HiEvents\Enterprise\Seating\Resources\EventSeatMapResourcePublic;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\Public\GetEventSeatMapPublicHandler;
use HiEvents\Http\Actions\Events\BasePublicEventAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetEventSeatMapPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly GetEventSeatMapPublicHandler $handler,
    ) {}

    public function __invoke(int $eventId): Response|JsonResponse
    {
        $result = $this->handler->handle($eventId);

        if (! $this->canUserViewEvent($result->event)) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(EventSeatMapResourcePublic::class, $result);
    }
}
