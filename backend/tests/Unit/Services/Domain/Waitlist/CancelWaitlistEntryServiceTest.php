<?php

namespace Tests\Unit\Services\Domain\Waitlist;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Waitlist\CancelWaitlistEntryService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelWaitlistEntryServiceTest extends TestCase
{
    private CancelWaitlistEntryService $service;
    private MockInterface|WaitlistEntryRepositoryInterface $waitlistEntryRepository;
    private MockInterface|OrderRepositoryInterface $orderRepository;
    private MockInterface|DatabaseManager $databaseManager;
    private MockInterface|ProductPriceRepositoryInterface $productPriceRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $productPrice = new ProductPriceDomainObject();
        $productPrice->setId(20);
        $productPrice->setProductId(99);

        $this->productPriceRepository
            ->shouldReceive('findById')
            ->with(20)
            ->andReturn($productPrice);

        $this->service = new CancelWaitlistEntryService(
            waitlistEntryRepository: $this->waitlistEntryRepository,
            orderRepository: $this->orderRepository,
            databaseManager: $this->databaseManager,
            productPriceRepository: $this->productPriceRepository,
        );
    }

    public function testSuccessfullyCancelsByToken(): void
    {
        Event::fake();

        $cancelToken = 'valid-cancel-token-123';

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getId')->andReturn(1);
        $entry->shouldReceive('getStatus')->andReturn(WaitlistEntryStatus::WAITING->name);
        $entry->shouldReceive('getOrderId')->andReturn(null);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes['status'] === WaitlistEntryStatus::CANCELLED->name
                        && isset($attributes['cancelled_at'])
                        && $attributes['order_id'] === null;
                }),
                ['id' => 1],
            );

        $cancelledEntry = new WaitlistEntryDomainObject();
        $cancelledEntry->setId(1);
        $cancelledEntry->setStatus(WaitlistEntryStatus::CANCELLED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($cancelledEntry);

        $result = $this->service->cancelByToken($cancelToken);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function testSuccessfullyCancelsByTokenWhenStatusIsOfferedDeletesOrder(): void
    {
        Event::fake();

        $cancelToken = 'valid-cancel-token-456';
        $orderId = 100;

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getId')->andReturn(2);
        $entry->shouldReceive('getStatus')->andReturn(WaitlistEntryStatus::OFFERED->name);
        $entry->shouldReceive('getOrderId')->andReturn($orderId);
        $entry->shouldReceive('getEventId')->andReturn(10);
        $entry->shouldReceive('getProductPriceId')->andReturn(20);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->orderRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                'id' => $orderId,
                'status' => OrderStatus::RESERVED->name,
            ]);

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes['status'] === WaitlistEntryStatus::CANCELLED->name
                        && isset($attributes['cancelled_at'])
                        && $attributes['order_id'] === null;
                }),
                ['id' => 2],
            );

        $cancelledEntry = new WaitlistEntryDomainObject();
        $cancelledEntry->setId(2);
        $cancelledEntry->setStatus(WaitlistEntryStatus::CANCELLED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($cancelledEntry);

        $result = $this->service->cancelByToken($cancelToken);

        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertDispatched(CapacityChangedEvent::class, function ($event) {
            return $event->eventId === 10 && $event->productId === 99;
        });
    }

    public function testSuccessfullyCancelsById(): void
    {
        Event::fake();

        $entryId = 5;
        $eventId = 1;

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getId')->andReturn($entryId);
        $entry->shouldReceive('getStatus')->andReturn(WaitlistEntryStatus::WAITING->name);
        $entry->shouldReceive('getOrderId')->andReturn(null);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $entryId,
                'event_id' => $eventId,
            ])
            ->andReturn($entry);

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attributes) {
                    return $attributes['status'] === WaitlistEntryStatus::CANCELLED->name
                        && isset($attributes['cancelled_at'])
                        && $attributes['order_id'] === null;
                }),
                ['id' => $entryId],
            );

        $cancelledEntry = new WaitlistEntryDomainObject();
        $cancelledEntry->setId($entryId);
        $cancelledEntry->setStatus(WaitlistEntryStatus::CANCELLED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with($entryId)
            ->andReturn($cancelledEntry);

        $result = $this->service->cancelById($entryId, $eventId);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function testThrowsExceptionForInvalidToken(): void
    {
        $invalidToken = 'invalid-token-does-not-exist';

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $invalidToken])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Waitlist entry not found');

        $this->service->cancelByToken($invalidToken);
    }

    public function testThrowsExceptionForInvalidEntryId(): void
    {
        $entryId = 999;
        $eventId = 1;

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $entryId,
                'event_id' => $eventId,
            ])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Waitlist entry not found');

        $this->service->cancelById($entryId, $eventId);
    }

    public function testThrowsExceptionForAlreadyCancelledEntry(): void
    {
        $cancelToken = 'already-cancelled-token';

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getStatus')->andReturn(WaitlistEntryStatus::CANCELLED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This waitlist entry cannot be cancelled');

        $this->service->cancelByToken($cancelToken);
    }

    public function testThrowsExceptionForPurchasedEntry(): void
    {
        $cancelToken = 'purchased-token';

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getStatus')->andReturn(WaitlistEntryStatus::PURCHASED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This waitlist entry cannot be cancelled');

        $this->service->cancelByToken($cancelToken);
    }

    public function testThrowsExceptionForExpiredOfferEntry(): void
    {
        $cancelToken = 'expired-offer-token';

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getStatus')->andReturn(WaitlistEntryStatus::OFFER_EXPIRED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This waitlist entry cannot be cancelled');

        $this->service->cancelByToken($cancelToken);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
