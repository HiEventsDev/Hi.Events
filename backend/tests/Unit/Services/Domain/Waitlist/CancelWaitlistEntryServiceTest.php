<?php

namespace Tests\Unit\Services\Domain\Waitlist;

use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
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

    private MockInterface|StripePaymentsRepositoryInterface $stripePaymentsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepositoryInterface::class);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $productPrice = new ProductPriceDomainObject;
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
            stripePaymentsRepository: $this->stripePaymentsRepository,
        );
    }

    private function makeEntry(
        int $id,
        string $status,
        ?int $orderId = null,
        int $eventId = 10,
        int $productPriceId = 20,
        int $eventOccurrenceId = 30,
    ): MockInterface|WaitlistEntryDomainObject {
        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getId')->andReturn($id);
        $entry->shouldReceive('getStatus')->andReturn($status);
        $entry->shouldReceive('getOrderId')->andReturn($orderId);
        $entry->shouldReceive('getEventId')->andReturn($eventId);
        $entry->shouldReceive('getProductPriceId')->andReturn($productPriceId);
        $entry->shouldReceive('getEventOccurrenceId')->andReturn($eventOccurrenceId);

        return $entry;
    }

    private function expectStatusUpdate(int $entryId): void
    {
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

        $cancelledEntry = new WaitlistEntryDomainObject;
        $cancelledEntry->setId($entryId);
        $cancelledEntry->setStatus(WaitlistEntryStatus::CANCELLED->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findById')
            ->once()
            ->with($entryId)
            ->andReturn($cancelledEntry);
    }

    public function test_successfully_cancels_by_token(): void
    {
        Event::fake();

        $cancelToken = 'valid-cancel-token-123';
        $entry = $this->makeEntry(1, WaitlistEntryStatus::WAITING->name);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->waitlistEntryRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(1)
            ->andReturn($entry);

        $this->expectStatusUpdate(1);

        $result = $this->service->cancelByToken($cancelToken);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_cancelling_offered_entry_without_stripe_payment_deletes_order(): void
    {
        Event::fake();

        $cancelToken = 'valid-cancel-token-456';
        $orderId = 100;
        $entry = $this->makeEntry(2, WaitlistEntryStatus::OFFERED->name, $orderId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->waitlistEntryRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(2)
            ->andReturn($entry);

        $this->stripePaymentsRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['order_id' => $orderId])
            ->andReturn(0);

        $this->orderRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                'id' => $orderId,
                'status' => OrderStatus::RESERVED->name,
            ]);

        $this->expectStatusUpdate(2);

        $result = $this->service->cancelByToken($cancelToken);

        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertDispatched(CapacityChangedEvent::class, function ($event) {
            return $event->eventId === 10
                && $event->productId === 99
                && $event->eventOccurrenceId === 30;
        });
    }

    public function test_cancelling_offered_entry_with_stripe_payment_abandons_order_instead_of_deleting(): void
    {
        Event::fake();

        $cancelToken = 'stripe-payment-token';
        $orderId = 200;
        $entry = $this->makeEntry(3, WaitlistEntryStatus::OFFERED->name, $orderId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['cancel_token' => $cancelToken])
            ->andReturn($entry);

        $this->waitlistEntryRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(3)
            ->andReturn($entry);

        $this->stripePaymentsRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(['order_id' => $orderId])
            ->andReturn(1);

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => OrderStatus::ABANDONED->name],
                [
                    'id' => $orderId,
                    'status' => OrderStatus::RESERVED->name,
                ],
            );

        $this->orderRepository->shouldNotReceive('deleteWhere');

        $this->expectStatusUpdate(3);

        $result = $this->service->cancelByToken($cancelToken);

        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertDispatched(CapacityChangedEvent::class);
    }

    public function test_throws_when_entry_became_uncancellable_after_lock(): void
    {
        $entry = $this->makeEntry(4, WaitlistEntryStatus::OFFERED->name, 300);
        $lockedEntry = $this->makeEntry(4, WaitlistEntryStatus::PURCHASED->name, 300);

        $this->waitlistEntryRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(4)
            ->andReturn($lockedEntry);

        $this->orderRepository->shouldNotReceive('deleteWhere');
        $this->orderRepository->shouldNotReceive('updateWhere');
        $this->waitlistEntryRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This waitlist entry cannot be cancelled');

        $this->service->cancelEntry($entry);
    }

    public function test_successfully_cancels_by_id(): void
    {
        Event::fake();

        $entryId = 5;
        $eventId = 1;
        $entry = $this->makeEntry($entryId, WaitlistEntryStatus::WAITING->name, eventId: $eventId);

        $this->waitlistEntryRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $entryId,
                'event_id' => $eventId,
            ])
            ->andReturn($entry);

        $this->waitlistEntryRepository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with($entryId)
            ->andReturn($entry);

        $this->expectStatusUpdate($entryId);

        $result = $this->service->cancelById($entryId, $eventId);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
        $this->assertEquals(WaitlistEntryStatus::CANCELLED->name, $result->getStatus());

        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function test_throws_exception_for_invalid_token(): void
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

    public function test_throws_exception_for_invalid_entry_id(): void
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

    public function test_throws_exception_for_already_cancelled_entry(): void
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

    public function test_throws_exception_for_purchased_entry(): void
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

    public function test_throws_exception_for_expired_offer_entry(): void
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
