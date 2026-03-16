<?php

namespace Tests\Unit\Jobs\Waitlist;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Jobs\Waitlist\ProcessExpiredWaitlistOffersJob;
use HiEvents\Jobs\Waitlist\SendWaitlistOfferExpiredEmailJob;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery as m;
use Tests\TestCase;

class ProcessExpiredWaitlistOffersJobTest extends TestCase
{
    private WaitlistEntryRepositoryInterface $repository;
    private OrderRepositoryInterface $orderRepository;
    private ProductPriceRepositoryInterface $productPriceRepository;
    private DatabaseManager $databaseManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = m::mock(WaitlistEntryRepositoryInterface::class);
        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->productPriceRepository = m::mock(ProductPriceRepositoryInterface::class);
        $this->databaseManager = m::mock(DatabaseManager::class);

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
    }

    public function testProcessesExpiredOffersAndDispatchesEmailAndEvent(): void
    {
        Bus::fake();
        Event::fake();

        $entry = new WaitlistEntryDomainObject();
        $entry->setId(1);
        $entry->setEventId(10);
        $entry->setProductPriceId(20);
        $entry->setOrderId(100);
        $entry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->repository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$entry]));

        $this->repository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(1)
            ->andReturn($entry);

        $this->orderRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with([
                'id' => 100,
                'status' => OrderStatus::RESERVED->name,
            ]);

        $this->repository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                m::on(function ($attributes) {
                    return $attributes['status'] === WaitlistEntryStatus::OFFER_EXPIRED->name
                        && $attributes['offer_token'] === null
                        && $attributes['offered_at'] === null
                        && $attributes['offer_expires_at'] === null
                        && $attributes['order_id'] === null;
                }),
                ['id' => 1],
            );

        $expiredEntry = new WaitlistEntryDomainObject();
        $expiredEntry->setId(1);
        $expiredEntry->setEventId(10);
        $expiredEntry->setProductPriceId(20);
        $expiredEntry->setStatus(WaitlistEntryStatus::OFFER_EXPIRED->name);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($expiredEntry);

        $job = new ProcessExpiredWaitlistOffersJob();
        $job->handle($this->repository, $this->orderRepository, $this->productPriceRepository, $this->databaseManager);

        Bus::assertDispatched(SendWaitlistOfferExpiredEmailJob::class);
        Event::assertDispatched(CapacityChangedEvent::class, function ($event) {
            return $event->eventId === 10 && $event->productId === 99;
        });
    }

    public function testSkipsOrderDeletionWhenNoOrderId(): void
    {
        Bus::fake();
        Event::fake();

        $entry = new WaitlistEntryDomainObject();
        $entry->setId(2);
        $entry->setEventId(10);
        $entry->setProductPriceId(20);
        $entry->setOrderId(null);
        $entry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->repository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$entry]));

        $this->repository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(2)
            ->andReturn($entry);

        $this->orderRepository->shouldNotReceive('deleteWhere');

        $this->repository
            ->shouldReceive('updateWhere')
            ->once();

        $expiredEntry = new WaitlistEntryDomainObject();
        $expiredEntry->setId(2);
        $expiredEntry->setEventId(10);
        $expiredEntry->setProductPriceId(20);
        $expiredEntry->setStatus(WaitlistEntryStatus::OFFER_EXPIRED->name);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with(2)
            ->andReturn($expiredEntry);

        $job = new ProcessExpiredWaitlistOffersJob();
        $job->handle($this->repository, $this->orderRepository, $this->productPriceRepository, $this->databaseManager);

        Bus::assertDispatched(SendWaitlistOfferExpiredEmailJob::class);
        Event::assertDispatched(CapacityChangedEvent::class);
    }

    public function testDoesNothingWhenNoExpiredEntries(): void
    {
        Bus::fake();
        Event::fake();

        $this->repository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection());

        $job = new ProcessExpiredWaitlistOffersJob();
        $job->handle($this->repository, $this->orderRepository, $this->productPriceRepository, $this->databaseManager);

        Bus::assertNotDispatched(SendWaitlistOfferExpiredEmailJob::class);
        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function testCatchesExceptionAndLogsError(): void
    {
        Event::fake();
        Bus::fake();

        $logged = false;
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to process expired waitlist offer', m::on(function ($context) use (&$logged) {
                $logged = true;
                return $context['entry_id'] === 1 && isset($context['error']);
            }));

        $entry = new WaitlistEntryDomainObject();
        $entry->setId(1);
        $entry->setEventId(10);
        $entry->setProductPriceId(20);
        $entry->setOrderId(100);
        $entry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->repository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$entry]));

        $this->repository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(1)
            ->andThrow(new \RuntimeException('DB connection lost'));

        $job = new ProcessExpiredWaitlistOffersJob();
        $job->handle($this->repository, $this->orderRepository, $this->productPriceRepository, $this->databaseManager);

        $this->assertTrue($logged, 'Error was logged for failed expired offer processing');
        Bus::assertNotDispatched(SendWaitlistOfferExpiredEmailJob::class);
        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    public function testSkipsEntryWhenStatusChangedBeforeLock(): void
    {
        Bus::fake();
        Event::fake();

        $entry = new WaitlistEntryDomainObject();
        $entry->setId(1);
        $entry->setEventId(10);
        $entry->setProductPriceId(20);
        $entry->setOrderId(100);
        $entry->setStatus(WaitlistEntryStatus::OFFERED->name);

        $this->repository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection([$entry]));

        $cancelledEntry = new WaitlistEntryDomainObject();
        $cancelledEntry->setId(1);
        $cancelledEntry->setStatus(WaitlistEntryStatus::CANCELLED->name);

        $this->repository
            ->shouldReceive('findByIdLocked')
            ->once()
            ->with(1)
            ->andReturn($cancelledEntry);

        $this->orderRepository->shouldNotReceive('deleteWhere');
        $this->repository->shouldNotReceive('updateWhere');

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($cancelledEntry);

        $job = new ProcessExpiredWaitlistOffersJob();
        $job->handle($this->repository, $this->orderRepository, $this->productPriceRepository, $this->databaseManager);

        Bus::assertNotDispatched(SendWaitlistOfferExpiredEmailJob::class);
        Event::assertNotDispatched(CapacityChangedEvent::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
