<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventSpamCheckDispatchServiceTest extends TestCase
{
    private EventSpamCheckService|MockInterface $eventSpamCheckService;

    private EventSpamCheckDispatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->eventSpamCheckService = Mockery::mock(EventSpamCheckService::class);
        $this->service = new EventSpamCheckDispatchService($this->eventSpamCheckService);
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
}
