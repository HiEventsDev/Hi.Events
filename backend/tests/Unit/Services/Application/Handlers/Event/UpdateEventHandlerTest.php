<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\Dispatcher;
use HiEvents\Exceptions\CannotChangeCurrencyException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateEventHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private HtmlPurifierService|MockInterface $purifier;

    private Dispatcher|MockInterface $dispatcher;

    private UpdateEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->purifier = Mockery::mock(HtmlPurifierService::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

        $this->handler = new UpdateEventHandler(
            $this->eventRepository,
            $this->dispatcher,
            $databaseManager,
            $this->orderRepository,
            $this->purifier,
            $this->occurrenceRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_when_changing_currency_with_completed_orders(): void
    {
        $existing = Mockery::mock(EventDomainObject::class);
        $existing->shouldReceive('getCurrency')->andReturn('USD');

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $completedOrder = Mockery::mock(OrderDomainObject::class);
        $this->orderRepository
            ->shouldReceive('findWhere')
            ->with([
                'event_id' => 1,
                'status' => OrderStatus::COMPLETED->name,
            ])
            ->andReturn(new Collection([$completedOrder]));

        $this->expectException(CannotChangeCurrencyException::class);

        $this->handler->handle(new UpdateEventDTO(
            title: 'Event',
            category: null,
            account_id: 5,
            id: 1,
            currency: 'EUR',
        ));
    }

    public function test_allows_currency_change_when_no_completed_orders(): void
    {
        $existing = Mockery::mock(EventDomainObject::class);
        $existing->shouldReceive('getCurrency')->andReturn('USD');
        $existing->shouldReceive('getCategory')->andReturn('OTHER');
        $existing->shouldReceive('getTimezone')->andReturn('UTC');
        $existing->shouldReceive('getType')->andReturn('OTHER_NOT_SINGLE');

        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->orderRepository
            ->shouldReceive('findWhere')
            ->with([
                'event_id' => 1,
                'status' => OrderStatus::COMPLETED->name,
            ])
            ->andReturn(new Collection);

        $this->purifier->shouldReceive('purify')->andReturn(null);

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn ($attrs) => ($attrs['currency'] ?? null) === 'EUR'),
                ['id' => 1, 'account_id' => 5],
            );

        $this->dispatcher->shouldReceive('dispatchEvent')->once();

        $result = $this->handler->handle(new UpdateEventDTO(
            title: 'Event',
            category: null,
            account_id: 5,
            id: 1,
            currency: 'EUR',
        ));

        $this->assertSame($reloaded, $result);
    }

    public function test_skips_completed_orders_check_when_currency_unchanged(): void
    {
        $existing = Mockery::mock(EventDomainObject::class);
        $existing->shouldReceive('getCurrency')->andReturn('USD');
        $existing->shouldReceive('getCategory')->andReturn('OTHER');
        $existing->shouldReceive('getTimezone')->andReturn('UTC');
        $existing->shouldReceive('getType')->andReturn('OTHER_NOT_SINGLE');

        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->orderRepository->shouldNotReceive('findWhere');
        $this->purifier->shouldReceive('purify')->andReturn(null);
        $this->eventRepository->shouldReceive('updateWhere')->once();
        $this->dispatcher->shouldReceive('dispatchEvent')->once();

        $result = $this->handler->handle(new UpdateEventDTO(
            title: 'Event',
            category: null,
            account_id: 5,
            id: 1,
            currency: 'USD',
        ));

        $this->assertSame($reloaded, $result);
    }
}
