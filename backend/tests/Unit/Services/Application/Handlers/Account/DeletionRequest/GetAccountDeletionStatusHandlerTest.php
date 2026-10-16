<?php

namespace Tests\Unit\Services\Application\Handlers\Account\DeletionRequest;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\Enums\AccountDeletionOutcome;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\GetAccountDeletionStatusHandler;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetAccountDeletionStatusHandlerTest extends TestCase
{
    private AccountDeletionService|MockInterface $deletionService;

    private GetAccountDeletionStatusHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deletionService = Mockery::mock(AccountDeletionService::class);
        $this->deletionService->shouldReceive('determineOutcome')->andReturn(AccountDeletionOutcome::ANONYMIZE);
        $this->handler = new GetAccountDeletionStatusHandler($this->deletionService);
    }

    public function test_lists_blocking_events_when_deletion_is_blocked_by_orders(): void
    {
        $events = collect([
            $this->makeEvent(7, 'Summer Festival'),
            $this->makeEvent(9, 'Winter Gala'),
        ]);
        $this->deletionService->shouldReceive('findActiveRequest')->once()->with(1)->andReturnNull();
        $this->deletionService->shouldReceive('getBlockingEvents')->once()->with(1)->andReturn($events);
        $this->deletionService->shouldReceive('describeCannotDeleteReason')
            ->once()
            ->with(false, $events)
            ->andReturn('Blocked');

        $status = $this->handler->handle(1);

        $this->assertFalse($status->canRequestDeletion);
        $this->assertSame('Blocked', $status->cannotDeleteReason);
        $this->assertSame(
            [['id' => 7, 'title' => 'Summer Festival'], ['id' => 9, 'title' => 'Winter Gala']],
            array_map(fn ($event) => $event->toArray(), $status->blockingEvents),
        );
    }

    public function test_has_no_blocking_events_when_deletion_is_allowed(): void
    {
        $this->deletionService->shouldReceive('findActiveRequest')->with(1)->andReturnNull();
        $this->deletionService->shouldReceive('getBlockingEvents')->with(1)->andReturn(collect());
        $this->deletionService->shouldReceive('describeCannotDeleteReason')->andReturnNull();

        $status = $this->handler->handle(1);

        $this->assertTrue($status->canRequestDeletion);
        $this->assertSame([], $status->blockingEvents);
    }

    public function test_skips_blocking_events_when_deletion_is_already_requested(): void
    {
        $this->deletionService->shouldReceive('findActiveRequest')->with(1)->andReturn(new AccountDeletionRequestDomainObject);
        $this->deletionService->shouldNotReceive('getBlockingEvents');
        $this->deletionService->shouldReceive('describeCannotDeleteReason')
            ->with(true, Mockery::on(fn ($events) => $events->isEmpty()))
            ->andReturn('Already requested');

        $status = $this->handler->handle(1);

        $this->assertFalse($status->canRequestDeletion);
        $this->assertSame([], $status->blockingEvents);
    }

    private function makeEvent(int $id, string $title): EventDomainObject
    {
        $event = new EventDomainObject;
        $event->setId($id);
        $event->setTitle($title);

        return $event;
    }
}
