<?php

namespace Tests\Unit\Services\Domain\Waitlist;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Waitlist\RevertWaitlistOffersForCancelledOrderService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RevertWaitlistOffersForCancelledOrderServiceTest extends TestCase
{
    private WaitlistEntryRepositoryInterface|MockInterface $waitlistEntryRepository;

    private ProductPriceRepositoryInterface|MockInterface $productPriceRepository;

    private RevertWaitlistOffersForCancelledOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);

        $this->service = new RevertWaitlistOffersForCancelledOrderService(
            $this->waitlistEntryRepository,
            $this->productPriceRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reverts_offered_entries_to_waiting_and_returns_capacity_events(): void
    {
        $orderId = 500;

        $entry = Mockery::mock(WaitlistEntryDomainObject::class);
        $entry->shouldReceive('getId')->andReturn(77);
        $entry->shouldReceive('getEventId')->andReturn(1);
        $entry->shouldReceive('getProductPriceId')->andReturn(9);
        $entry->shouldReceive('getEventOccurrenceId')->andReturn(3);

        $this->waitlistEntryRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([
                'order_id' => $orderId,
                ['status', 'in', [WaitlistEntryStatus::OFFERED->name]],
            ])
            ->andReturn(collect([$entry]));

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [
                    'status' => WaitlistEntryStatus::WAITING->name,
                    'order_id' => null,
                    'offered_at' => null,
                    'offer_expires_at' => null,
                    'offer_token' => null,
                ],
                [
                    'id' => 77,
                    'status' => WaitlistEntryStatus::OFFERED->name,
                ],
            );

        $productPrice = Mockery::mock(ProductPriceDomainObject::class);
        $productPrice->shouldReceive('getProductId')->andReturn(42);

        $this->productPriceRepository
            ->shouldReceive('findById')
            ->once()
            ->with(9)
            ->andReturn($productPrice);

        $capacityEvents = $this->service->revertOffersForOrder($orderId);

        $this->assertCount(1, $capacityEvents);

        $capacityEvent = $capacityEvents[0];
        $this->assertInstanceOf(CapacityChangedEvent::class, $capacityEvent);
        $this->assertSame(1, $capacityEvent->eventId);
        $this->assertSame(CapacityChangeDirection::INCREASED, $capacityEvent->direction);
        $this->assertSame(42, $capacityEvent->productId);
        $this->assertSame(9, $capacityEvent->productPriceId);
        $this->assertSame(3, $capacityEvent->eventOccurrenceId);
    }

    public function test_returns_no_events_when_order_has_no_offered_entries(): void
    {
        $this->waitlistEntryRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect());

        $this->waitlistEntryRepository->shouldNotReceive('updateWhere');
        $this->productPriceRepository->shouldNotReceive('findById');

        $this->assertSame([], $this->service->revertOffersForOrder(123));
    }
}
