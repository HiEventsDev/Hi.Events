<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSpamCheckDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\ApproveSpamEventHandler;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ApproveSpamEventHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventSpamCheckRepositoryInterface|MockInterface $eventSpamCheckRepository;

    private EventSpamCheckService|MockInterface $eventSpamCheckService;

    private ApproveSpamEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventSpamCheckRepository = Mockery::mock(EventSpamCheckRepositoryInterface::class);
        $this->eventSpamCheckService = Mockery::mock(EventSpamCheckService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

        $this->handler = new ApproveSpamEventHandler(
            $this->eventRepository,
            $this->eventSpamCheckRepository,
            $this->eventSpamCheckService,
            $databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_approves_event_and_marks_checks_approved(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1, 'status' => EventSpamCheckStatus::FLAGGED->name])
            ->andReturn(new EventSpamCheckDomainObject);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1])
            ->andReturn((new EventDomainObject)->setTitle('Title')->setDescription('Description'));

        $this->eventSpamCheckService
            ->shouldReceive('hashContent')
            ->with('Title', 'Description')
            ->andReturn('current-hash');

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => EventStatus::LIVE->name],
                ['id' => 1, 'status' => EventStatus::PENDING_MANUAL_REVIEW->name],
            )
            ->andReturn(1);

        $this->eventSpamCheckRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attrs) {
                    return $attrs['status'] === EventSpamCheckStatus::APPROVED->name
                        && $attrs['content_hash'] === 'current-hash'
                        && $attrs['reviewed_by_user_id'] === 99
                        && $attrs['reviewed_at'] !== null;
                }),
                ['event_id' => 1, 'status' => EventSpamCheckStatus::FLAGGED->name],
            )
            ->andReturn(1);

        $this->handler->handle(1, 99);
    }

    public function test_throws_when_no_flagged_check_exists(): void
    {
        $this->eventSpamCheckRepository->shouldReceive('findFirstWhere')->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(1, 99);
    }

    public function test_throws_when_event_not_pending_review(): void
    {
        $this->eventSpamCheckRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn(new EventSpamCheckDomainObject);

        $this->eventRepository->shouldReceive('findFirstWhere')->andReturn(new EventDomainObject);
        $this->eventRepository->shouldReceive('updateWhere')->andReturn(0);
        $this->eventSpamCheckRepository->shouldNotReceive('updateWhere');

        $this->expectException(ValidationException::class);

        $this->handler->handle(1, 99);
    }
}
