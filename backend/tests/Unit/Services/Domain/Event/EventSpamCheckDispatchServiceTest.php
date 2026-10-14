<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventSpamCheckDispatchServiceTest extends TestCase
{
    private EventSpamCheckService|MockInterface $eventSpamCheckService;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventSpamCheckDispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->eventSpamCheckService = Mockery::mock(EventSpamCheckService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->service = new EventSpamCheckDispatchService($this->eventSpamCheckService, $this->eventRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dispatches_for_a_single_event(): void
    {
        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();

        $this->service->dispatchForEvent(5);

        Bus::assertDispatched(EventSpamCheckJob::class);
    }

    public function test_does_not_dispatch_when_disabled(): void
    {
        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnFalse();

        $this->service->dispatchForEvent(5);

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    public function test_dispatches_for_every_live_event_of_an_organizer(): void
    {
        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventRepository->shouldReceive('findWhere')->once()->with([
            'organizer_id' => 3,
            'status' => 'LIVE',
        ])->andReturn(collect([
            (new EventDomainObject)->setId(11),
            (new EventDomainObject)->setId(12),
        ]));

        $this->service->dispatchForOrganizer(3);

        Bus::assertDispatchedTimes(EventSpamCheckJob::class, 2);
    }

    public function test_does_not_look_up_organizer_events_when_disabled(): void
    {
        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnFalse();
        $this->eventRepository->shouldNotReceive('findWhere');

        $this->service->dispatchForOrganizer(3);

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }
}
