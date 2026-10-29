<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderCheckoutAccess;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderPublicDTO;
use HiEvents\Services\Domain\Order\OfflinePaymentInstructionsRenderService;
use HiEvents\Services\Domain\Order\PaymentLink\OrderCheckoutAccessService;
use HiEvents\Services\Domain\Order\PaymentLink\OrderPaymentLinkTokenService;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionAccessService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetOrderPublicHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCheckoutAccessService $checkoutAccessService,
        private readonly OrderPaymentLinkTokenService $paymentLinkTokenService,
        private readonly OfflinePaymentInstructionsRenderService $offlinePaymentInstructionsRenderService,
        private readonly AccountSubscriptionAccessService $accountSubscriptionAccessService,
    ) {}

    public function handle(GetOrderPublicDTO $getOrderData): OrderDomainObject
    {
        $order = $this->getOrderDomainObject($getOrderData);

        if (! $order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        if ($order->getStatus() === OrderStatus::RESERVED->name) {
            $this->authorizeReservedOrder($order);
        } elseif ($order->hasPaymentLink()) {
            $this->markPaymentLinkAccess($order, $this->checkoutAccessService->resolve($order));
        }

        $this->offlinePaymentInstructionsRenderService->renderForOrder($order);

        $organizer = $order->getEvent()?->getOrganizer();
        $organizer?->setBrandingRemoved(
            $this->accountSubscriptionAccessService->hasActiveSubscription($organizer->getAccountId())
        );

        return $order;
    }

    private function authorizeReservedOrder(OrderDomainObject $order): void
    {
        $access = $this->checkoutAccessService->resolve($order);

        if (! $access->canPay()) {
            throw new UnauthorizedException(
                __('Sorry, we could not verify your session. Please restart your order.')
            );
        }

        $this->markPaymentLinkAccess($order, $access);
    }

    private function markPaymentLinkAccess(OrderDomainObject $order, OrderCheckoutAccess $access): void
    {
        $order
            ->setAccessedViaPaymentLink($access === OrderCheckoutAccess::PAYMENT_LINK)
            ->setPaymentLinkUrl($this->paymentLinkTokenService->urlFor($order));
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
                            ),
                        ],
                        name: ProductDomainObjectAbstract::SINGULAR_NAME,
                    ),
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        nested: [
                            new Relationship(
                                domainObject: EventLocationDomainObject::class,
                                nested: [
                                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                                ],
                                name: 'event_location',
                            ),
                        ],
                        name: 'event_occurrence',
                    ),
                    new Relationship(domainObject: AttendeeCheckInDomainObject::class, name: 'check_ins'),
                ],
            ))
            ->loadRelation(new Relationship(domainObject: InvoiceDomainObject::class))
            ->loadRelation(SeatClaimDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrderItemDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        nested: [
                            new Relationship(
                                domainObject: EventLocationDomainObject::class,
                                nested: [
                                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                                ],
                                name: 'event_location',
                            ),
                        ],
                        name: 'event_occurrence',
                    ),
                ],
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
                    ),
                    new Relationship(
                        domainObject: EventLocationDomainObject::class,
                        nested: [
                            new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                        ],
                        name: 'event_location',
                    ),
                    new Relationship(
                        domainObject: EventOccurrenceDomainObject::class,
                        nested: [
                            new Relationship(
                                domainObject: EventLocationDomainObject::class,
                                nested: [
                                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                                ],
                                name: 'event_location',
                            ),
                        ],
                    ),
                ],
                name: EventDomainObjectAbstract::SINGULAR_NAME
            ));
        }

        return $orderQuery->findByShortId($getOrderData->orderShortId);
    }
}
