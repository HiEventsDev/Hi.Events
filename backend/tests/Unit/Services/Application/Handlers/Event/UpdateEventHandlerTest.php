<?php

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Events\Dispatcher;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventHandler;
use HiEvents\Services\Domain\Organizer\OrganizerFetchService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Mockery as m;
use Tests\TestCase;

class UpdateEventHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;

    private OrganizerFetchService $organizerFetchService;

    private UpdateEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $orderRepository = m::mock(OrderRepositoryInterface::class);
        $dispatcher = m::mock(Dispatcher::class);
        $purifier = m::mock(HtmlPurifierService::class);
        $databaseManager = m::mock(DatabaseManager::class);
        $this->organizerFetchService = m::mock(OrganizerFetchService::class);

        $databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn ($callback) => $callback());

        $purifier->shouldReceive('purify')
            ->andReturnUsing(fn ($value) => $value);

        $dispatcher->shouldReceive('dispatchEvent')->byDefault();

        $this->handler = new UpdateEventHandler(
            $this->eventRepository,
            $dispatcher,
            $databaseManager,
            $orderRepository,
            $purifier,
            $this->organizerFetchService,
        );
    }

    private function makeExistingEvent(int $organizerId = 5): EventDomainObject
    {
        $event = new EventDomainObject;
        $event->setId(1)
            ->setAccountId(10)
            ->setOrganizerId($organizerId)
            ->setCurrency('USD')
            ->setTimezone('UTC')
            ->setCategory('OTHER')
            ->setTitle('Existing');

        return $event;
    }

    private function makeDto(?int $organizerId = null): UpdateEventDTO
    {
        return new UpdateEventDTO(
            title: 'Updated',
            category: null,
            account_id: 10,
            id: 1,
            start_date: '2026-01-01 10:00:00',
            end_date: null,
            description: 'desc',
            timezone: 'UTC',
            currency: 'USD',
            location: null,
            location_details: null,
            status: 'DRAFT',
            organizer_id: $organizerId,
        );
    }

    public function test_reassigns_organizer_when_organizer_id_provided_and_different(): void
    {
        $existing = $this->makeExistingEvent(organizerId: 5);

        $this->eventRepository->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->organizerFetchService->shouldReceive('fetchOrganizer')
            ->once()
            ->with(7, 10)
            ->andReturn(new OrganizerDomainObject);

        $this->eventRepository->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes, array $where) {
                return ($attributes['organizer_id'] ?? null) === 7
                    && $where === ['id' => 1, 'account_id' => 10];
            })
            ->andReturn(1);

        $this->handler->handle($this->makeDto(organizerId: 7));

        $this->assertTrue(true);
    }

    public function test_does_not_include_organizer_id_when_omitted(): void
    {
        $existing = $this->makeExistingEvent(organizerId: 5);

        $this->eventRepository->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->organizerFetchService->shouldNotReceive('fetchOrganizer');

        $this->eventRepository->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes) {
                return ! array_key_exists('organizer_id', $attributes);
            })
            ->andReturn(1);

        $this->handler->handle($this->makeDto(organizerId: null));

        $this->assertTrue(true);
    }

    public function test_does_not_reassign_when_organizer_id_matches_current(): void
    {
        $existing = $this->makeExistingEvent(organizerId: 5);

        $this->eventRepository->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->organizerFetchService->shouldNotReceive('fetchOrganizer');

        $this->eventRepository->shouldReceive('updateWhere')
            ->once()
            ->withArgs(function (array $attributes) {
                return ! array_key_exists('organizer_id', $attributes);
            })
            ->andReturn(1);

        $this->handler->handle($this->makeDto(organizerId: 5));

        $this->assertTrue(true);
    }

    public function test_rejects_cross_account_organizer(): void
    {
        $existing = $this->makeExistingEvent(organizerId: 5);

        $this->eventRepository->shouldReceive('findFirstWhere')
            ->andReturn($existing);

        $this->organizerFetchService->shouldReceive('fetchOrganizer')
            ->once()
            ->with(99, 10)
            ->andThrow(new OrganizerNotFoundException('Organizer 99 not found'));

        $this->eventRepository->shouldNotReceive('updateWhere');

        $this->expectException(OrganizerNotFoundException::class);

        $this->handler->handle($this->makeDto(organizerId: 99));
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
