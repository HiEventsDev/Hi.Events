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
use HiEvents\Services\Application\Handlers\Admin\ConfirmSpamEventHandler;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ConfirmSpamEventHandlerTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventSpamCheckRepositoryInterface|MockInterface $eventSpamCheckRepository;

    private ConfirmSpamEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventSpamCheckRepository = Mockery::mock(EventSpamCheckRepositoryInterface::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

        $this->handler = new ConfirmSpamEventHandler(
            $this->eventRepository,
            $this->eventSpamCheckRepository,
            $databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_confirms_spam_without_changing_event_status(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => 1, 'status' => EventSpamCheckStatus::FLAGGED->name])
            ->andReturn(new EventSpamCheckDomainObject);

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 1])
            ->andReturn((new EventDomainObject)->setStatus(EventStatus::PENDING_MANUAL_REVIEW->name));

        $this->eventRepository->shouldNotReceive('updateWhere');

        $this->eventSpamCheckRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(function ($attrs) {
                    return $attrs['status'] === EventSpamCheckStatus::CONFIRMED_SPAM->name
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

        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn((new EventDomainObject)->setStatus(EventStatus::LIVE->name));

        $this->eventSpamCheckRepository->shouldNotReceive('updateWhere');

        $this->expectException(ValidationException::class);

        $this->handler->handle(1, 99);
    }
}
