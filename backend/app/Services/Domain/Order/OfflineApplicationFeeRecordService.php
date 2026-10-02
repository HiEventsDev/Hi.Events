<?php

namespace HiEvents\Services\Domain\Order;

use Brick\Math\Exception\MathException;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class OfflineApplicationFeeRecordService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderApplicationFeeCalculationService $orderApplicationFeeCalculationService,
        private readonly OrderApplicationFeeService $orderApplicationFeeService,
    ) {}

    /**
     * @throws MathException
     */
    public function record(OrderDomainObject $order): void
    {
        /** @var EventDomainObject $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: OrganizerConfigurationDomainObject::class,
                        name: 'organizer_configuration',
                    ),
                ],
                name: 'organizer'
            ))
            ->findById($order->getEventId());

        $config = $event->getOrganizer()?->getOrganizerConfiguration();
        if (! $config) {
            return;
        }

        $this->orderApplicationFeeService->createOrderApplicationFee(
            orderId: $order->getId(),
            applicationFeeAmountMinorUnit: $this->orderApplicationFeeCalculationService->calculateApplicationFee(
                configuration: $config,
                order: $order,
            )?->netApplicationFee?->toMinorUnit() ?? 0,
            orderApplicationFeeStatus: OrderApplicationFeeStatus::AWAITING_PAYMENT,
            paymentMethod: PaymentProviders::OFFLINE,
            currency: $order->getCurrency(),
        );
    }
}
