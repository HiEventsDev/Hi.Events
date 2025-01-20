<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderPublicDTO;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetOrderPublicHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface         $orderRepository,
        private readonly CheckoutSessionManagementService $sessionIdentifierService
    )
    {
    }

    public function handle(GetOrderPublicDTO $getOrderData): OrderDomainObject
    {
        $order = $this->getOrderDomainObject($getOrderData);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        if ($order->getStatus() === OrderStatus::RESERVED->name) {
            $this->verifySessionId($order->getSessionId());
        }

        return $order;
    }

    private function verifySessionId(string $orderSessionId): void
    {
        if (!$this->sessionIdentifierService->verifySession($orderSessionId)) {
            throw new UnauthorizedException(
                __('Sorry, we could not verify your session. Please restart your order.')
            );
        }
    }

    private function getOrderDomainObject(GetOrderPublicDTO $getOrderData): ?OrderDomainObject
    {
        $orderQuery = $this->orderRepository
            ->loadRelation(new Relationship(
                domainObject: AttendeeDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: ProductDomainObject::class,
                        nested: [
                            new Relationship(
                                domainObject: ProductPriceDomainObject::class,
                            )
                        ],
                        name: ProductDomainObjectAbstract::SINGULAR_NAME,
                    )
                ],
            ))
            ->loadRelation(new Relationship(domainObject: InvoiceDomainObject::class))
            ->loadRelation(new Relationship(
                domainObject: OrderItemDomainObject::class,
            ));

        if ($getOrderData->includeEventInResponse) {
            $orderQuery->loadRelation(new Relationship(
                domainObject: EventDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventSettingDomainObject::class,
                    ),
                    new Relationship(
                        domainObject: OrganizerDomainObject::class,
                        name: OrganizerDomainObjectAbstract::SINGULAR_NAME,
                    ),
                    new Relationship(
                        domainObject: ImageDomainObject::class,
                    )
                ],
                name: EventDomainObjectAbstract::SINGULAR_NAME
            ));
        }

        return $orderQuery->findByShortId($getOrderData->orderShortId);
    }
}
