<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\Request;

class EnsureOrderBelongsToEvent
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(Request $request, Closure $next)
    {
        $eventId = $request->route('event_id');
        $orderShortId = $request->route('order_short_id');

        $order = is_numeric($eventId) && is_string($orderShortId)
            ? $this->orderRepository->findFirstWhere(
                where: [
                    OrderDomainObjectAbstract::SHORT_ID => $orderShortId,
                    OrderDomainObjectAbstract::EVENT_ID => (int) $eventId,
                ],
                columns: [OrderDomainObjectAbstract::ID],
            )
            : null;

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $next($request);
    }
}
