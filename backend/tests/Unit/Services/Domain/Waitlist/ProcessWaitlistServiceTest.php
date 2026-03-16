<?php

namespace Tests\Unit\Services\Domain\Waitlist;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Exceptions\NoCapacityAvailableException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\Waitlist\SendWaitlistOfferEmailJob;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Waitlist\ProcessWaitlistService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessWaitlistServiceTest extends TestCase
{
    private ProcessWaitlistService $service;
    private MockInterface|WaitlistEntryRepositoryInterface $waitlistEntryRepository;
    private MockInterface|DatabaseManager $databaseManager;
    private MockInterface|OrderManagementService $orderManagementService;
    private MockInterface|OrderItemProcessingService $orderItemProcessingService;
    private MockInterface|ProductRepositoryInterface $productRepository;
    private MockInterface|AvailableProductQuantitiesFetchService $availableQuantitiesService;
    private MockInterface|ProductPriceRepositoryInterface $productPriceRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->orderManagementService = Mockery::mock(OrderManagementService::class);
        $this->orderItemProcessingService = Mockery::mock(OrderItemProcessingService::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->availableQuantitiesService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);

        $this->waitlistEntryRepository
            ->shouldReceive('lockForProductPrice')
            ->zeroOrMoreTimes();

        $this->databaseManager
            ->shouldReceive('statement')
            ->withArgs(function ($sql, $params) {
                return $sql === 'SELECT pg_advisory_xact_lock(?)' && is_array($params);
            })
            ->zeroOrMoreTimes()
            ->andReturn(true);

        $this->service = new ProcessWaitlistService(
            waitlistEntryRepository: $this->waitlistEntryRepository,
            databaseManager: $this->databaseManager,
            orderManagementService: $this->orderManagementService,
            orderItemProcessingService: $this->orderItemProcessingService,
            productRepository: $this->productRepository,
            availableQuantitiesService: $this->availableQuantitiesService,
            productPriceRepository: $this->productPriceRepository,
        );
    }

    private function createMockEvent(int $id = 1, string $currency = 'USD'): EventDomainObject
    {
        $event = new EventDomainObject();
        $event->setId($id);
        $event->setCurrency($currency);
        return $event;
    }

    private function createMockEventSettings(?int $timeoutMinutes = 30): EventSettingDomainObject
    {
        $eventSettings = new EventSettingDomainObject();
        $eventSettings->setWaitlistOfferTimeoutMinutes($timeoutMinutes);
        return $eventSettings;
    }

    private function mockAvailableQuantities(int $eventId, int $priceId, int $quantityAvailable = 10): void
    {
        $this->availableQuantitiesService
            ->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, true)
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    new AvailableProductQuantitiesDTO(
                        product_id: 99,
                        price_id: $priceId,
                        product_title: 'Test Product',
                        price_label: 'Test Price',
                        quantity_available: $quantityAvailable,
                        quantity_reserved: 0,
                        initial_quantity_available: $quantityAvailable,
                    ),
                ])
            ));
    }

    private function mockOrderCreation(): OrderDomainObject
    {
        $order = new OrderDomainObject();
        $order->setId(100);
        $order->setShortId('o_test123');

        $this->orderManagementService
            ->shouldReceive('createNewOrder')
            ->once()
            ->withArgs(function () {
                $args = func_get_args();
                return count($args) >= 7 && is_string($args[6]) && !empty($args[6]);
            })
            ->andReturn($order);

        $productPrice = new ProductPriceDomainObject();
        $productPrice->setId(1);
        $productPrice->setProductId(10);

        $this->productPriceRepository
            ->shouldReceive('findById')
            ->andReturn($productPrice);

        $product = new ProductDomainObject();
        $product->setId(10);
        $product->setProductPrices(new Collection([$productPrice]));

        $this->productRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->productRepository
            ->shouldReceive('findById')
            ->andReturn($product);

        $orderItem = new OrderItemDomainObject();
        $this->orderItemProcessingService
            ->shouldReceive('process')
            ->once()
            ->andReturn(new Collection([$orderItem]));

        $this->orderManagementService
            ->shouldReceive('updateOrderTotals')
            ->once()
            ->andReturn($order);

        return $order;
    }

    public function testSuccessfullyOffersToNextWaitingEntry(): void
    {
        Bus::fake();

        $productPriceId = 10;
        $quantity = 2;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId);

        $waitingEntry = Mockery::mock(WaitlistEntryDomainObject::class);
        $waitingEntry->shouldReceive('getId')->andReturn(1);
        $waitingEntry->shouldReceive('getLocale')->andReturn('en');
        $waitingEntry->shouldReceive('getProductPriceId')->andReturn($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('getNextWaitingEntries')
            ->once()
            ->with($productPriceId, Mockery::any())
            ->andReturn(new Collection([$waitingEntry]));

        $order = $this->mockOrderCreation();

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) use ($order) {
                    return $attributes['status'] === WaitlistEntryStatus::OFFERED->name
                        && !empty($attributes['offer_token'])
                        && $attributes['offered_at'] !== null
                        && $attributes['offer_expires_at'] !== null
                        && $attributes['order_id'] === $order->getId();
                }),
                ['id' => 1],
            );

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId(1);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);
        $updatedEntry->setOfferToken('some-token');
        $updatedEntry->setOrderId($order->getId());

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($updatedEntry);

        $result = $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);

        $this->assertCount(1, $result);
        $this->assertEquals(WaitlistEntryStatus::OFFERED->name, $result->first()->getStatus());
        $this->assertEquals($order->getId(), $result->first()->getOrderId());

        Bus::assertDispatched(SendWaitlistOfferEmailJob::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $sessionProp = $reflection->getProperty('sessionIdentifier');
            return !empty($sessionProp->getValue($job));
        });
    }

    public function testSetsCorrectOfferTokenAndOfferExpiresAt(): void
    {
        Bus::fake();

        $productPriceId = 10;
        $quantity = 1;
        $timeoutMinutes = 60;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings($timeoutMinutes);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId);

        $waitingEntry = Mockery::mock(WaitlistEntryDomainObject::class);
        $waitingEntry->shouldReceive('getId')->andReturn(5);
        $waitingEntry->shouldReceive('getLocale')->andReturn('en');
        $waitingEntry->shouldReceive('getProductPriceId')->andReturn($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('getNextWaitingEntries')
            ->once()
            ->with($productPriceId, Mockery::any())
            ->andReturn(new Collection([$waitingEntry]));

        $this->mockOrderCreation();

        $capturedAttributes = null;
        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) use (&$capturedAttributes) {
                    $capturedAttributes = $attributes;
                    return true;
                }),
                ['id' => 5],
            );

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId(5);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with(5)
            ->andReturn($updatedEntry);

        $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);

        $this->assertNotNull($capturedAttributes);
        $this->assertEquals(WaitlistEntryStatus::OFFERED->name, $capturedAttributes['status']);
        $this->assertNotEmpty($capturedAttributes['offer_token']);
        $this->assertNotNull($capturedAttributes['offered_at']);
        $this->assertNotNull($capturedAttributes['offer_expires_at']);
        $this->assertNotNull($capturedAttributes['order_id']);
    }

    public function testCreatesReservedOrderWhenOffering(): void
    {
        Bus::fake();

        $productPriceId = 10;
        $quantity = 1;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings(30);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId);

        $waitingEntry = Mockery::mock(WaitlistEntryDomainObject::class);
        $waitingEntry->shouldReceive('getId')->andReturn(1);
        $waitingEntry->shouldReceive('getLocale')->andReturn('en');
        $waitingEntry->shouldReceive('getProductPriceId')->andReturn($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('getNextWaitingEntries')
            ->once()
            ->with($productPriceId, Mockery::any())
            ->andReturn(new Collection([$waitingEntry]));

        $order = new OrderDomainObject();
        $order->setId(100);
        $order->setShortId('o_test123');

        $this->orderManagementService
            ->shouldReceive('createNewOrder')
            ->once()
            ->with(
                Mockery::on(fn($v) => $v === $event->getId()),
                Mockery::on(fn($v) => $v instanceof EventDomainObject),
                Mockery::on(fn($v) => $v === 30),
                Mockery::on(fn($v) => $v === 'en'),
                Mockery::on(fn($v) => $v === null),
                Mockery::on(fn($v) => $v === null),
                Mockery::on(fn($v) => is_string($v) && !empty($v)),
            )
            ->andReturn($order);

        $productPrice = new ProductPriceDomainObject();
        $productPrice->setId(1);
        $productPrice->setProductId(10);

        $this->productPriceRepository
            ->shouldReceive('findById')
            ->andReturn($productPrice);

        $product = new ProductDomainObject();
        $product->setId(10);
        $product->setProductPrices(new Collection([$productPrice]));

        $this->productRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->productRepository
            ->shouldReceive('findById')
            ->with(10)
            ->andReturn($product);

        $orderItem = new OrderItemDomainObject();
        $this->orderItemProcessingService
            ->shouldReceive('process')
            ->once()
            ->andReturn(new Collection([$orderItem]));

        $this->orderManagementService
            ->shouldReceive('updateOrderTotals')
            ->once()
            ->andReturn($order);

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn($attrs) => $attrs['order_id'] === 100),
                ['id' => 1],
            );

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId(1);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);
        $updatedEntry->setOrderId(100);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($updatedEntry);

        $result = $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);

        $this->assertCount(1, $result);
        $this->assertEquals(100, $result->first()->getOrderId());
    }

    public function testThrowsWhenNoWaitingEntries(): void
    {
        $productPriceId = 10;
        $quantity = 2;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('getNextWaitingEntries')
            ->once()
            ->with($productPriceId, Mockery::any())
            ->andReturn(new Collection());

        $this->expectException(NoCapacityAvailableException::class);

        $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);
    }

    public function testCapsOffersAtAvailableCapacity(): void
    {
        Bus::fake();

        $productPriceId = 10;
        $quantity = 3;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId, 1);

        $waitingEntry = Mockery::mock(WaitlistEntryDomainObject::class);
        $waitingEntry->shouldReceive('getId')->andReturn(1);
        $waitingEntry->shouldReceive('getLocale')->andReturn('en');
        $waitingEntry->shouldReceive('getProductPriceId')->andReturn($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('getNextWaitingEntries')
            ->once()
            ->with($productPriceId, Mockery::any())
            ->andReturn(new Collection([$waitingEntry]));

        $this->mockOrderCreation();

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once();

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId(1);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($updatedEntry);

        $result = $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);

        $this->assertCount(1, $result);
    }

    public function testThrowsWhenNoCapacityAtAll(): void
    {
        $productPriceId = 10;
        $quantity = 2;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId, 0);

        $this->expectException(NoCapacityAvailableException::class);

        $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);
    }

    public function testOfferExpiresAtUsesDefaultWhenTimeoutNotSet(): void
    {
        Bus::fake();

        $productPriceId = 10;
        $quantity = 1;
        $event = $this->createMockEvent();
        $eventSettings = $this->createMockEventSettings(null);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->mockAvailableQuantities($event->getId(), $productPriceId);

        $waitingEntry = Mockery::mock(WaitlistEntryDomainObject::class);
        $waitingEntry->shouldReceive('getId')->andReturn(1);
        $waitingEntry->shouldReceive('getLocale')->andReturn('en');
        $waitingEntry->shouldReceive('getProductPriceId')->andReturn($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('getNextWaitingEntries')
            ->once()
            ->with($productPriceId, Mockery::any())
            ->andReturn(new Collection([$waitingEntry]));

        $this->mockOrderCreation();

        $capturedAttributes = null;
        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) use (&$capturedAttributes) {
                    $capturedAttributes = $attributes;
                    return true;
                }),
                ['id' => 1],
            );

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId(1);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($updatedEntry);

        $this->service->offerToNext($productPriceId, $quantity, $event, $eventSettings);

        $this->assertNotNull($capturedAttributes['offer_expires_at']);
    }

    public function testOfferSpecificEntrySuccessfully(): void
    {
        Bus::fake();

        $entryId = 7;
        $eventId = 1;
        $productPriceId = 10;
        $event = $this->createMockEvent($eventId);
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $entry = new WaitlistEntryDomainObject();
        $entry->setId($entryId);
        $entry->setStatus(WaitlistEntryStatus::WAITING->name);
        $entry->setLocale('en');
        $entry->setProductPriceId($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $entryId, 'event_id' => $eventId])
            ->andReturn($entry);

        $this->mockAvailableQuantities($eventId, $productPriceId, 5);

        $order = $this->mockOrderCreation();

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) use ($order) {
                    return $attributes['status'] === WaitlistEntryStatus::OFFERED->name
                        && !empty($attributes['offer_token'])
                        && $attributes['offered_at'] !== null
                        && $attributes['order_id'] === $order->getId();
                }),
                ['id' => $entryId],
            );

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId($entryId);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);
        $updatedEntry->setOrderId($order->getId());

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with($entryId)
            ->andReturn($updatedEntry);

        $result = $this->service->offerSpecificEntry($entryId, $eventId, $event, $eventSettings);

        $this->assertCount(1, $result);
        $this->assertEquals(WaitlistEntryStatus::OFFERED->name, $result->first()->getStatus());

        Bus::assertDispatched(SendWaitlistOfferEmailJob::class);
    }

    public function testOfferSpecificEntryThrowsWhenEntryNotFound(): void
    {
        $entryId = 99;
        $eventId = 1;
        $event = $this->createMockEvent($eventId);
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $entryId, 'event_id' => $eventId])
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        $this->service->offerSpecificEntry($entryId, $eventId, $event, $eventSettings);
    }

    public function testOfferSpecificEntryThrowsWhenStatusNotOfferable(): void
    {
        $entryId = 7;
        $eventId = 1;
        $event = $this->createMockEvent($eventId);
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $entry = new WaitlistEntryDomainObject();
        $entry->setId($entryId);
        $entry->setStatus(WaitlistEntryStatus::PURCHASED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($entry);

        $this->expectException(ResourceConflictException::class);

        $this->service->offerSpecificEntry($entryId, $eventId, $event, $eventSettings);
    }

    public function testOfferSpecificEntryAllowsReOfferForExpiredEntries(): void
    {
        Bus::fake();

        $entryId = 7;
        $eventId = 1;
        $productPriceId = 10;
        $event = $this->createMockEvent($eventId);
        $eventSettings = $this->createMockEventSettings(60);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $entry = new WaitlistEntryDomainObject();
        $entry->setId($entryId);
        $entry->setStatus(WaitlistEntryStatus::OFFER_EXPIRED->name);
        $entry->setLocale('en');
        $entry->setProductPriceId($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($entry);

        $this->mockAvailableQuantities($eventId, $productPriceId, 3);

        $this->mockOrderCreation();

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once();

        $updatedEntry = new WaitlistEntryDomainObject();
        $updatedEntry->setId($entryId);
        $updatedEntry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($updatedEntry);

        $result = $this->service->offerSpecificEntry($entryId, $eventId, $event, $eventSettings);

        $this->assertCount(1, $result);
        Bus::assertDispatched(SendWaitlistOfferEmailJob::class);
    }

    public function testOfferSpecificEntryThrowsWhenNoCapacityAvailable(): void
    {
        $entryId = 7;
        $eventId = 1;
        $productPriceId = 10;
        $event = $this->createMockEvent($eventId);
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $entry = new WaitlistEntryDomainObject();
        $entry->setId($entryId);
        $entry->setStatus(WaitlistEntryStatus::WAITING->name);
        $entry->setLocale('en');
        $entry->setProductPriceId($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $entryId, 'event_id' => $eventId])
            ->andReturn($entry);

        $this->mockAvailableQuantities($eventId, $productPriceId, 0);

        $this->expectException(NoCapacityAvailableException::class);

        $this->service->offerSpecificEntry($entryId, $eventId, $event, $eventSettings);
    }

    public function testOfferSpecificEntryThrowsWhenCapacityFullyOffered(): void
    {
        $entryId = 7;
        $eventId = 1;
        $productPriceId = 10;
        $event = $this->createMockEvent($eventId);
        $eventSettings = $this->createMockEventSettings();

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $entry = new WaitlistEntryDomainObject();
        $entry->setId($entryId);
        $entry->setStatus(WaitlistEntryStatus::WAITING->name);
        $entry->setLocale('en');
        $entry->setProductPriceId($productPriceId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => $entryId, 'event_id' => $eventId])
            ->andReturn($entry);

        $this->mockAvailableQuantities($eventId, $productPriceId, 0);

        $this->expectException(NoCapacityAvailableException::class);

        $this->service->offerSpecificEntry($entryId, $eventId, $event, $eventSettings);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
