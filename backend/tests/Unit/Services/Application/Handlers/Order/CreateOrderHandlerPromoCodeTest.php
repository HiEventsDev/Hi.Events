<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\PromoCode\PromoCodeUsageValidationService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CreateOrderHandlerPromoCodeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const EVENT_ID = 1;
    private const PROMO_CODE_ID = 5;

    public function testPromoCodeIsDroppedWhenNotUsable(): void
    {
        $captured = $this->runHandler(isUsable: false);

        $this->assertNull($captured);
    }

    public function testPromoCodeIsAppliedWhenUsable(): void
    {
        $captured = $this->runHandler(isUsable: true);

        $this->assertInstanceOf(PromoCodeDomainObject::class, $captured);
        $this->assertSame(self::PROMO_CODE_ID, $captured->getId());
    }

    private function runHandler(bool $isUsable): mixed
    {
        $promoCode = (new PromoCodeDomainObject())
            ->setId(self::PROMO_CODE_ID)
            ->setCode('save50');

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $eventSettings->shouldReceive('getOrderTimeoutInMinutes')->andReturn(15);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getCurrency')->andReturn('USD');

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->with(self::EVENT_ID)->andReturn($event);

        $promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $promoCodeRepository->shouldReceive('findFirstWhere')->andReturn($promoCode);

        $usageValidationService = Mockery::mock(PromoCodeUsageValidationService::class);
        $usageValidationService->shouldReceive('isPromoCodeUsable')->with($promoCode)->andReturn($isUsable);

        $affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);

        $orderManagementService = Mockery::mock(OrderManagementService::class);
        $orderManagementService->shouldReceive('deleteExistingOrders');

        $captured = false;
        $order = Mockery::mock(OrderDomainObject::class);
        $orderManagementService
            ->shouldReceive('createNewOrder')
            ->andReturnUsing(function ($eventId, $event, $timeOut, $locale, $promo, $affiliate, $sessionId) use (&$captured, $order) {
                $captured = $promo;
                return $order;
            });
        $orderManagementService->shouldReceive('updateOrderTotals')->andReturn($order);

        $orderItemProcessingService = Mockery::mock(OrderItemProcessingService::class);
        $orderItemProcessingService->shouldReceive('process')->andReturn(collect());

        $availabilityService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $availabilityService->shouldReceive('getAvailableProductQuantities')
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect(),
                capacities: collect(),
            ));

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('statement')->andReturn(true);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn($callback) => $callback());

        $handler = new CreateOrderHandler(
            $eventRepository,
            $promoCodeRepository,
            $usageValidationService,
            $affiliateRepository,
            $orderManagementService,
            $orderItemProcessingService,
            $availabilityService,
            $databaseManager,
        );

        $dto = new CreateOrderPublicDTO(
            products: collect(),
            is_user_authenticated: true,
            session_identifier: 'sess-123',
            order_locale: 'en',
            promo_code: 'save50',
            affiliate_code: null,
        );

        $handler->handle(self::EVENT_ID, $dto);

        return $captured;
    }
}
