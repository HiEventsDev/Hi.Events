<?php

namespace HiEvents\Http\Actions\Waitlist\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Waitlist\GetWaitlistStatsRequest;
use HiEvents\Services\Application\Handlers\Waitlist\GetWaitlistStatsHandler;
use Illuminate\Http\JsonResponse;

class GetWaitlistStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetWaitlistStatsHandler $handler,
    )
    {
    }

    public function __invoke(GetWaitlistStatsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $validated = $request->validated();

        $stats = $this->handler->handle(
            $eventId,
            isset($validated['event_occurrence_id']) ? (int) $validated['event_occurrence_id'] : null,
        );

        return $this->jsonResponse([
            'total' => $stats->total,
            'waiting' => $stats->waiting,
            'offered' => $stats->offered,
            'purchased' => $stats->purchased,
            'cancelled' => $stats->cancelled,
            'expired' => $stats->expired,
            'products' => array_map(fn($p) => [
                'product_price_id' => $p->product_price_id,
                'product_title' => $p->product_title,
                'waiting' => $p->waiting,
                'offered' => $p->offered,
                'available' => $p->available,
            ], $stats->products),
        ]);
    }
}
