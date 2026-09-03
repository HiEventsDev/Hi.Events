<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\EventPendingReviewException;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventStatusDTO;
use HiEvents\Services\Application\Handlers\Event\UpdateEventStatusHandler;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

class UpdateEventStatusHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private AccountRepositoryInterface|MockInterface $accountRepository;

    private EventSpamCheckService|MockInterface $eventSpamCheckService;

    private UpdateEventStatusHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->eventSpamCheckService = Mockery::mock(EventSpamCheckService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

        $this->handler = new UpdateEventStatusHandler(
            $this->eventRepository,
            $this->accountRepository,
            new NullLogger,
            $databaseManager,
            $this->eventSpamCheckService,
        );

        $this->accountRepository
            ->shouldReceive('findById')
            ->andReturn((new AccountDomainObject)->setAccountVerifiedAt('2024-01-01 00:00:00'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_when_event_is_pending_manual_review(): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->makeEvent(EventStatus::PENDING_MANUAL_REVIEW->name));

        $this->eventRepository->shouldNotReceive('updateWhere');

        $this->expectException(EventPendingReviewException::class);

        $this->handler->handle($this->makeDTO(EventStatus::DRAFT->name));
    }

    public function test_dispatches_spam_check_when_event_becomes_live(): void
    {
        $this->arrangeStatusUpdate(currentStatus: EventStatus::DRAFT->name);

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventSpamCheckService->shouldReceive('hashContent')->andReturn('hash');

        $this->handler->handle($this->makeDTO(EventStatus::LIVE->name));

        Bus::assertDispatched(EventSpamCheckJob::class);
    }

    public function test_does_not_dispatch_spam_check_when_unpublishing(): void
    {
        $this->arrangeStatusUpdate(currentStatus: EventStatus::LIVE->name);

        $this->handler->handle($this->makeDTO(EventStatus::DRAFT->name));

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    public function test_does_not_dispatch_spam_check_when_disabled(): void
    {
        $this->arrangeStatusUpdate(currentStatus: EventStatus::DRAFT->name);

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnFalse();

        $this->handler->handle($this->makeDTO(EventStatus::LIVE->name));

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    public function test_does_not_dispatch_spam_check_when_event_already_live(): void
    {
        $this->arrangeStatusUpdate(currentStatus: EventStatus::LIVE->name);

        $this->handler->handle($this->makeDTO(EventStatus::LIVE->name));

        Bus::assertNotDispatched(EventSpamCheckJob::class);
    }

    private function arrangeStatusUpdate(string $currentStatus): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($this->makeEvent($currentStatus), $this->makeEvent($currentStatus));

        $this->eventRepository->shouldReceive('updateWhere')->once()->andReturn(1);
    }

    private function makeEvent(string $status): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId(1)
            ->setStatus($status)
            ->setTitle('Event Title')
            ->setDescription('Event description');
    }

    private function makeDTO(string $status): UpdateEventStatusDTO
    {
        return new UpdateEventStatusDTO(
            status: $status,
            eventId: 1,
            accountId: 5,
        );
    }
}
