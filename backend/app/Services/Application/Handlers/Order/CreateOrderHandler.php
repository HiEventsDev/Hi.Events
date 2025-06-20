<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AffiliateDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\AffiliateStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\UnauthorizedException;
use Throwable;

class CreateOrderHandler
{
    public function __construct(
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly AffiliateRepositoryInterface $affiliateRepository,
        private readonly OrderManagementService       $orderManagementService,
        private readonly OrderItemProcessingService   $orderItemProcessingService,
        private readonly DatabaseManager              $databaseManager,
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
            $affiliate = $this->getAffiliate($createOrderPublicDTO, $eventId);

            if ($deleteExistingOrdersForSession) {
                $this->orderManagementService->deleteExistingOrders($eventId, $createOrderPublicDTO->session_identifier);
            }

            $order = $this->orderManagementService->createNewOrder(
                eventId: $eventId,
                event: $event,
                timeOutMinutes: $event->getEventSettings()?->getOrderTimeoutInMinutes(),
                locale: $createOrderPublicDTO->order_locale,
                promoCode: $promoCode,
                affiliate: $affiliate,
                sessionId: $createOrderPublicDTO->session_identifier,
            );

            $orderItems = $this->orderItemProcessingService->process(
                order: $order,
                productsOrderDetails: $createOrderPublicDTO->products,
                event: $event,
                promoCode: $promoCode,
            );

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

    private function getAffiliate(CreateOrderPublicDTO $createOrderPublicDTO, int $eventId): ?AffiliateDomainObject
    {
        if ($createOrderPublicDTO->affiliate_code === null) {
            return null;
        }

        return $this->affiliateRepository->findFirstWhere([
            AffiliateDomainObjectAbstract::CODE => strtoupper(trim($createOrderPublicDTO->affiliate_code)),
            AffiliateDomainObjectAbstract::EVENT_ID => $eventId,
            AffiliateDomainObjectAbstract::STATUS => AffiliateStatus::ACTIVE->value,
        ]);
    }

    public function validateEventStatus(EventDomainObject $event, CreateOrderPublicDTO $createOrderPublicDTO): void
    {
        if (!$createOrderPublicDTO->is_user_authenticated && $event->getStatus() !== EventStatus::LIVE->name) {
            throw new UnauthorizedException(
                __('This event is not live.')
            );
        }
    }
}
