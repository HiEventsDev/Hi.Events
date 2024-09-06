<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetOrderActionPublic extends BaseAction
{
    private OrderRepositoryInterface $orderRepository;

    private CheckoutSessionManagementService $sessionIdentifierService;

    public function __construct(OrderRepositoryInterface $orderRepository, CheckoutSessionManagementService $sessionIdentifierService)
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
        if (!$this->sessionIdentifierService->verifySession($orderSessionId)) {
            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please restart your order.'));
        }
    }
}
