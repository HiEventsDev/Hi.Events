<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Mail\Order\OrderSummary;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailer;

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
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->findFirstWhere([
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
                OrderDomainObjectAbstract::ID => $orderId,
            ]);

        if (!$order) {
            return $this->notFoundResponse();
        }

        if ($order->isOrderCompleted()) {
            $event = $this->eventRepository->findById($order->getEventId());
            $this->mailer->to($order->getEmail())->send(new OrderSummary(
                $order,
                $event,
                $event->getOrganizer(),
            ));
        }

        return $this->noContentResponse();
    }
}
