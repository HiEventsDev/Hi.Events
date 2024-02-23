<?php

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\Response;
use Illuminate\Mail\Mailer;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\Generated\OrderDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Mail\OrderSummary;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;

class ResendOrderConfirmationAction extends BaseAction
{
    private EventRepositoryInterface $eventRepository;

    private OrderRepositoryInterface $orderRepository;

    private Mailer $mailer;

    public function __construct(
        EventRepositoryInterface $eventRepository,
        OrderRepositoryInterface $orderRepository,
        Mailer                   $mailer,
    )
    {
        $this->eventRepository = $eventRepository;
        $this->orderRepository = $orderRepository;
        $this->mailer = $mailer;
    }

    /**
     * @todo - move this to a handler
     */
    public function __invoke(int $eventId, int $orderId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findFirstWhere([
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
                OrderDomainObjectAbstract::ID => $orderId,
            ]);

        if (!$order) {
            return $this->notFoundResponse();
        }

        if ($order->isOrderCompleted()) {
            $event = $this->eventRepository->findById($order->getEventId());
            $this->mailer->to($order->getEmail())->send(new OrderSummary($order, $event));
        }

        return $this->noContentResponse();
    }
}
