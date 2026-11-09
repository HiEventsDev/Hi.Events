<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\WidgetRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\PromoCode\PromoCodeUsageValidationService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CreateOrderHandlerPromoCodeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const EVENT_ID = 1;

    private const PROMO_CODE_ID = 5;

    private const GATED_PRODUCT_ID = 10;

    public function test_promo_code_is_dropped_when_not_usable(): void
    {
        $captured = $this->runHandler(isUsable: false);

        $this->assertNull($captured);
    }

    public function test_promo_code_is_applied_when_usable(): void
    {
        $captured = $this->runHandler(isUsable: true);

        $this->assertInstanceOf(PromoCodeDomainObject::class, $captured);
        $this->assertSame(self::PROMO_CODE_ID, $captured->getId());
    }

    public function test_a_product_hidden_without_a_code_is_refused_as_an_invalid_code_when_the_code_is_used_up(): void
    {
        $this->expectException(ValidationException::class);

        $this->runHandler(isUsable: false, products: collect([$this->gatedProduct()]));
    }

    public function test_a_product_hidden_without_a_code_is_refused_when_the_code_does_not_apply_to_it(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->runHandler(isUsable: true, products: collect([$this->gatedProduct()]), applicableProductIds: [self::GATED_PRODUCT_ID + 1]);
    }

    public function test_a_product_hidden_without_a_code_is_sold_with_a_usable_code_that_applies_to_it(): void
    {
        $captured = $this->runHandler(isUsable: true, products: collect([$this->gatedProduct()]));

        $this->assertSame(self::PROMO_CODE_ID, $captured->getId());
    }

    private function gatedProduct(): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId(self::GATED_PRODUCT_ID)
            ->setIsHiddenWithoutPromoCode(true);
    }

    private function runHandler(bool $isUsable, ?Collection $products = null, array $applicableProductIds = [self::GATED_PRODUCT_ID]): mixed
    {
        $products ??= collect();

        $promoCode = (new PromoCodeDomainObject)
            ->setId(self::PROMO_CODE_ID)
            ->setCode('save50')
            ->setApplicableProductIds($applicableProductIds);

        $productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $productRepository->shouldReceive('findWhereIn')->andReturn($products);

        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $eventSettings->shouldReceive('getOrderTimeoutInMinutes')->andReturn(15);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getCurrency')->andReturn('USD');
        $event->shouldReceive('getStatus')->andReturn('LIVE');

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
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $occurrenceEligibilityService = Mockery::mock(OccurrencePurchaseEligibilityService::class);

        $handler = new CreateOrderHandler(
            $eventRepository,
            $promoCodeRepository,
            $usageValidationService,
            $affiliateRepository,
            $orderManagementService,
            $orderItemProcessingService,
            $availabilityService,
            $occurrenceEligibilityService,
            $databaseManager,
            Mockery::mock(SeatClaimService::class)->shouldIgnoreMissing(),
            new SeatingEventLockService(
                new TransactionLockService($databaseManager),
                Mockery::mock(EventSeatMapLookupService::class)->shouldIgnoreMissing(),
                Mockery::mock(SeatedProductLookupService::class)->shouldIgnoreMissing(),
            ),
            Mockery::mock(WidgetRepositoryInterface::class),
            $productRepository,
        );

        $dto = new CreateOrderPublicDTO(
            products: $products->map(fn (ProductDomainObject $product) => new ProductOrderDetailsDTO(
                product_id: $product->getId(),
                quantities: collect(),
            )),
            can_order_unpublished_event: true,
            session_identifier: 'sess-123',
            order_locale: 'en',
            promo_code: 'save50',
            affiliate_code: null,
        );

        $handler->handle(self::EVENT_ID, $dto);

        return $captured;
    }
}
