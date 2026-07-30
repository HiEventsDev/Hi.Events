<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Events\Dispatcher;
use HiEvents\Exceptions\CannotChangeCurrencyException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
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

    public function test_throws_when_changing_currency_with_paid_orders(): void
    {
        $existing = Mockery::mock(EventDomainObject::class);
        $existing->shouldReceive('getCurrency')->andReturn('USD');

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1, ['total_gross', '>', 0]])
            ->andReturn(Mockery::mock(OrderDomainObject::class));

        $this->orderRepository->shouldNotReceive('updateWhere');

        $this->expectException(CannotChangeCurrencyException::class);

        $this->handler->handle(new UpdateEventDTO(
            title: 'Event',
            category: null,
            account_id: 5,
            id: 1,
            currency: 'EUR',
        ));
    }

    public function test_allows_currency_change_and_updates_free_orders_when_no_paid_orders(): void
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
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1, ['total_gross', '>', 0]])
            ->andReturnNull();

        $this->purifier->shouldReceive('purify')->andReturn(null);

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn ($attrs) => ($attrs['currency'] ?? null) === 'EUR'),
                ['id' => 1, 'account_id' => 5],
            );

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['currency' => 'EUR'],
                ['event_id' => 1, ['total_gross', '=', 0]],
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

    public function test_skips_orders_check_when_currency_unchanged(): void
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

        $this->orderRepository->shouldNotReceive('findFirstWhere');
        $this->orderRepository->shouldNotReceive('updateWhere');
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
