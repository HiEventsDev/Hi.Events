<?php

declare(strict_types=1);

namespace TicketKitten\Service\Handler\Order;

use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\UnauthorizedException;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\EventSettingDomainObject;
use TicketKitten\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\PromoCodeDomainObject;
use TicketKitten\DomainObjects\Status\EventStatus;
use TicketKitten\Http\DataTransferObjects\CreateOrderPublicDTO;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Service\Common\Order\OrderItemProcessingService;
use TicketKitten\Service\Common\Order\OrderManagementService;

readonly class CreateOrderHandler
{
    public function __construct(
        private EventRepositoryInterface     $eventRepository,
        private PromoCodeRepositoryInterface $promoCodeRepository,
        private OrderManagementService       $orderManagementService,
        private OrderItemProcessingService   $orderItemProcessingService,
        private DatabaseManager              $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(
        int                  $eventId,
        CreateOrderPublicDTO $createOrderPublicDTO,
        bool                 $deleteExistingOrdersForSession = true
    ): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventId, $createOrderPublicDTO, $deleteExistingOrdersForSession) {
            $event = $this->eventRepository
                ->loadRelation(EventSettingDomainObject::class)
                ->findById($eventId);

            $this->validateEventStatus($event, $createOrderPublicDTO);

            $promoCode = $this->getPromoCode($createOrderPublicDTO, $eventId);

            if ($deleteExistingOrdersForSession) {
                $this->orderManagementService->deleteExistingOrders($eventId);
            }

            $order = $this->orderManagementService->createNewOrder(
                eventId: $eventId,
                event: $event,
                timeOutMinutes: $event->getEventSettings()?->getOrderTimeoutInMinutes(),
                promoCode: $promoCode
            );

            $orderItems = $this->orderItemProcessingService->process($order, $createOrderPublicDTO->tickets, $event, $promoCode);

            return $this->orderManagementService->updateOrderTotals($order, $orderItems);
        });
    }


    private function getPromoCode(CreateOrderPublicDTO $createOrderPublicDTO, int $eventId): ?PromoCodeDomainObject
    {
        if ($createOrderPublicDTO->promo_code === null) {
            return null;
        }

        $promoCode = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::CODE => strtolower(trim($createOrderPublicDTO->promo_code)),
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($promoCode?->isValid()) {
            return $promoCode;
        }

        return null;
    }

    public function validateEventStatus(EventDomainObject $event, CreateOrderPublicDTO $createOrderPublicDTO): void
    {
        if ($event->getStatus() !== EventStatus::LIVE->name && !$createOrderPublicDTO->is_user_authenticated) {
            throw new UnauthorizedException(
                __('The event is not live and the user is not authenticated.')
            );
        }
    }
}
