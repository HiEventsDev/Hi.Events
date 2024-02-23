<?php

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\Status\OrderStatus;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\DomainObjects\TicketPriceDomainObject;
use TicketKitten\Exceptions\UnauthorizedException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Models\TicketPrice;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Resources\Order\OrderResourcePublic;
use TicketKitten\Service\Common\Session\SessionIdentifierService;

class GetOrderActionPublic extends BaseAction
{
    private OrderRepositoryInterface $orderRepository;

    private SessionIdentifierService $sessionIdentifierService;

    public function __construct(OrderRepositoryInterface $orderRepository, SessionIdentifierService $sessionIdentifierService)
    {
        $this->orderRepository = $orderRepository;
        $this->sessionIdentifierService = $sessionIdentifierService;
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(
                domainObject: AttendeeDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: TicketDomainObject::class,
                        nested: [
                            new Relationship(
                                domainObject: TicketPriceDomainObject::class,
                            )
                        ],
                        name: 'ticket',
                    )
                ],
            ))
            ->loadRelation(new Relationship(
                domainObject: OrderItemDomainObject::class,
            ))
            ->findByShortId($orderShortId);

        if (!$order) {
            throw new NotFoundHttpException();
        }

        if ($order->getStatus() === OrderStatus::RESERVED->name) {
            $this->verifySessionId($order->getSessionId());
        }

        return $this->resourceResponse(OrderResourcePublic::class, $order);
    }

    private function verifySessionId(string $orderSessionId): void
    {
        if (!$this->sessionIdentifierService->verifyIdentifier($orderSessionId)) {
            throw new UnauthorizedException();
        }
    }
}
