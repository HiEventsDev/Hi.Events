<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Events\Dispatcher;
use HiEvents\Exceptions\CannotChangeCurrencyException;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventHandler;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
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

    private DatabaseManager|MockInterface $databaseManager;

    private EventSpamCheckService|MockInterface $eventSpamCheckService;

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

        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

        $this->eventSpamCheckService = Mockery::mock(EventSpamCheckService::class);

        $this->handler = new UpdateEventHandler(
            $this->eventRepository,
            $this->dispatcher,
            $this->databaseManager,
            $this->orderRepository,
            $this->purifier,
            $this->occurrenceRepository,
            $this->eventSpamCheckService,
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

        $this->databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?)', [1]);

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
        $existing->shouldReceive('getStatus')->andReturn('DRAFT');

        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->databaseManager
            ->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?)', [1]);

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
        $existing->shouldReceive('getStatus')->andReturn('DRAFT');

        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->databaseManager->shouldNotReceive('statement');
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

    public function test_dispatches_spam_check_when_live_event_content_changes(): void
    {
        $existing = $this->liveEvent(title: 'Old Title', description: 'Old description');

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventSpamCheckService
            ->shouldReceive('hashContent')
            ->with('New Title', null)
            ->andReturn('new-hash');

        $this->handleContentUpdate($existing, title: 'New Title');

        Bus::assertDispatched(EventSpamCheckJob::class);
    }

    public function test_does_not_dispatch_spam_check_when_content_unchanged(): void
    {
        $existing = $this->liveEvent(title: 'Event', description: 'Same description');

        $this->eventSpamCheckService->shouldNotReceive('hashContent');

        $this->handleContentUpdate($existing, title: 'Event', description: 'Same description');

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    public function test_does_not_dispatch_spam_check_when_disabled(): void
    {
        $existing = $this->liveEvent(title: 'Old Title', description: 'Old description');

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnFalse();

        $this->handleContentUpdate($existing, title: 'New Title');

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    public function test_partial_update_without_description_preserves_existing_description(): void
    {
        $existing = $this->liveEvent(title: 'Event', description: 'Existing description');

        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->purifier->shouldNotReceive('purify');

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn ($attrs) => $attrs['description'] === 'Existing description'),
                ['id' => 1, 'account_id' => 5],
            );

        $this->dispatcher->shouldReceive('dispatchEvent')->once();

        $this->handler->handle(new UpdateEventDTO(
            title: 'Event',
            category: null,
            account_id: 5,
            id: 1,
            description_provided: false,
            currency: 'USD',
        ));

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    public function test_explicit_null_description_clears_existing_description(): void
    {
        $this->expectNotToPerformAssertions();

        $existing = $this->liveEvent(title: 'Event', description: 'Existing description');

        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->purifier->shouldReceive('purify')->with(null)->andReturnNull();
        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnFalse();

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn ($attrs) => $attrs['description'] === null),
                ['id' => 1, 'account_id' => 5],
            );

        $this->dispatcher->shouldReceive('dispatchEvent')->once();

        $this->handler->handle(new UpdateEventDTO(
            title: 'Event',
            category: null,
            account_id: 5,
            id: 1,
            description: null,
            currency: 'USD',
        ));
    }

    public function test_does_not_dispatch_spam_check_when_event_not_live(): void
    {
        $existing = Mockery::mock(EventDomainObject::class);
        $existing->shouldReceive('getCurrency')->andReturn('USD');
        $existing->shouldReceive('getCategory')->andReturn('OTHER');
        $existing->shouldReceive('getTimezone')->andReturn('UTC');
        $existing->shouldReceive('getType')->andReturn('OTHER_NOT_SINGLE');
        $existing->shouldReceive('getStatus')->andReturn('DRAFT');

        $this->eventSpamCheckService->shouldNotReceive('isEnabled');

        $this->handleContentUpdate($existing, title: 'New Title');

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    private function liveEvent(string $title, string $description): EventDomainObject|MockInterface
    {
        $existing = Mockery::mock(EventDomainObject::class);
        $existing->shouldReceive('getCurrency')->andReturn('USD');
        $existing->shouldReceive('getCategory')->andReturn('OTHER');
        $existing->shouldReceive('getTimezone')->andReturn('UTC');
        $existing->shouldReceive('getType')->andReturn('OTHER_NOT_SINGLE');
        $existing->shouldReceive('getStatus')->andReturn('LIVE');
        $existing->shouldReceive('getId')->andReturn(1);
        $existing->shouldReceive('getTitle')->andReturn($title);
        $existing->shouldReceive('getDescription')->andReturn($description);

        return $existing;
    }

    private function handleContentUpdate(
        EventDomainObject|MockInterface $existing,
        string $title,
        ?string $description = null,
    ): void {
        $reloaded = Mockery::mock(EventDomainObject::class);
        $reloaded->shouldReceive('getId')->andReturn(1);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($existing, $reloaded);

        $this->purifier->shouldReceive('purify')->andReturn($description);
        $this->eventRepository->shouldReceive('updateWhere')->once();
        $this->dispatcher->shouldReceive('dispatchEvent')->once();

        $this->handler->handle(new UpdateEventDTO(
            title: $title,
            category: null,
            account_id: 5,
            id: 1,
            currency: 'USD',
        ));
    }
}
