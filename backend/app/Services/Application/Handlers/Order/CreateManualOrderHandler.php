<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateManualOrderDTO;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateManualOrderHandler
{
    public function __construct(
        private readonly EventRepositoryInterface               $eventRepository,
        private readonly OrderRepositoryInterface               $orderRepository,
        private readonly AttendeeRepositoryInterface            $attendeeRepository,
        private readonly PromoCodeRepositoryInterface           $promoCodeRepository,
        private readonly ProductPriceRepositoryInterface        $productPriceRepository,
        private readonly OrderManagementService                 $orderManagementService,
        private readonly OrderItemProcessingService             $orderItemProcessingService,
        private readonly AvailableProductQuantitiesFetchService $availableProductQuantitiesFetchService,
        private readonly ProductQuantityUpdateService           $productQuantityUpdateService,
        private readonly DomainEventDispatcherService           $domainEventDispatcherService,
        private readonly DatabaseManager                        $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateManualOrderDTO $dto): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            $this->databaseManager->statement('SELECT pg_advisory_xact_lock(?)', [$dto->event_id]);

            $event = $this->eventRepository
                ->loadRelation(EventSettingDomainObject::class)
                ->findById($dto->event_id);

            $promoCode = $this->getPromoCode($dto->promo_code, $dto->event_id);

            $this->validateProductAvailability($dto);

            $order = $this->createOrder($dto, $event);

            $orderItems = $this->orderItemProcessingService->process(
                order: $order,
                productsOrderDetails: $dto->products,
                event: $event,
                promoCode: $promoCode,
            );

            $order = $this->orderManagementService->updateOrderTotals(
                $order,
                $orderItems,
                $this->orderItemProcessingService->getPerOrderTaxesAndFees(),
            );

            $this->createAttendeesFromOrderItems($order);

            $this->productQuantityUpdateService->updateQuantitiesFromOrder($order);

            $this->finaliseOrder($order, $dto, $event);

            return $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($order->getId());
        });
    }

    private function createOrder(CreateManualOrderDTO $dto, EventDomainObject $event): OrderDomainObject
    {
        return $this->orderRepository->create([
            OrderDomainObjectAbstract::EVENT_ID => $dto->event_id,
            OrderDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::ORDER_PREFIX),
            OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::NO_PAYMENT_REQUIRED->name,
            OrderDomainObjectAbstract::CURRENCY => $event->getCurrency(),
            OrderDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ORDER_PREFIX),
            OrderDomainObjectAbstract::IS_MANUALLY_CREATED => true,
            OrderDomainObjectAbstract::FIRST_NAME => $dto->first_name,
            OrderDomainObjectAbstract::LAST_NAME => $dto->last_name,
            OrderDomainObjectAbstract::EMAIL => $dto->email,
            OrderDomainObjectAbstract::LOCALE => $dto->locale,
            OrderDomainObjectAbstract::NOTES => $dto->notes,
        ]);
    }

    private function createAttendeesFromOrderItems(OrderDomainObject $order): void
    {
        $inserts = [];

        foreach ($order->getOrderItems() as $orderItem) {
            /** @var OrderItemDomainObject $orderItem */
            for ($i = 0; $i < $orderItem->getQuantity(); $i++) {
                $inserts[] = [
                    AttendeeDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                    AttendeeDomainObjectAbstract::PRODUCT_ID => $orderItem->getProductId(),
                    AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID => $orderItem->getProductPriceId(),
                    AttendeeDomainObjectAbstract::STATUS => AttendeeStatus::ACTIVE->name,
                    AttendeeDomainObjectAbstract::EMAIL => $order->getEmail(),
                    AttendeeDomainObjectAbstract::FIRST_NAME => $order->getFirstName(),
                    AttendeeDomainObjectAbstract::LAST_NAME => $order->getLastName(),
                    AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
                    AttendeeDomainObjectAbstract::PUBLIC_ID => IdHelper::publicId(IdHelper::ATTENDEE_PREFIX),
                    AttendeeDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::ATTENDEE_PREFIX),
                    AttendeeDomainObjectAbstract::LOCALE => $order->getLocale(),
                ];
            }
        }

        if (!empty($inserts)) {
            $this->attendeeRepository->insert($inserts);
        }
    }

    private function finaliseOrder(OrderDomainObject $order, CreateManualOrderDTO $dto, EventDomainObject $event): void
    {
        $isFree = $order->getTotalGross() <= 0;

        $paymentStatus = $isFree
            ? OrderPaymentStatus::NO_PAYMENT_REQUIRED->name
            : OrderPaymentStatus::PAYMENT_RECEIVED->name;

        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::PAYMENT_STATUS => $paymentStatus,
        ]);

        /** @var EventSettingDomainObject|null $eventSettings */
        $eventSettings = $event->getEventSettings();

        event(new OrderStatusChangedEvent(
            order: $order,
            sendEmails: $dto->send_confirmation_email,
            createInvoice: $eventSettings?->getEnableInvoicing() ?? false,
        ));

        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(
                type: DomainEventType::ORDER_CREATED,
                orderId: $order->getId(),
            )
        );
    }

    private function getPromoCode(?string $promoCode, int $eventId): ?PromoCodeDomainObject
    {
        if ($promoCode === null) {
            return null;
        }

        $code = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::CODE => strtolower(trim($promoCode)),
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($code?->isValid()) {
            return $code;
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    private function validateProductAvailability(CreateManualOrderDTO $dto): void
    {
        $availability = $this->availableProductQuantitiesFetchService
            ->getAvailableProductQuantities($dto->event_id, ignoreCache: true);

        foreach ($dto->products as $product) {
            foreach ($product->quantities as $priceQuantity) {
                if ($priceQuantity->quantity <= 0) {
                    continue;
                }

                $available = $availability->productQuantities
                    ->where('product_id', $product->product_id)
                    ->where('price_id', $priceQuantity->price_id)
                    ->first();

                if (!$available || $available->quantity_available < $priceQuantity->quantity) {
                    throw ValidationException::withMessages([
                        'products' => __('Insufficient availability for the selected products.'),
                    ]);
                }
            }
        }
    }
}
