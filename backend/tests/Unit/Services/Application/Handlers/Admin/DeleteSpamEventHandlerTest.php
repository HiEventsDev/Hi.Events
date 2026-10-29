<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSpamCheckDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DeleteSpamEventHandler;
use HiEvents\Services\Application\Handlers\Admin\DTO\DeleteSpamEventDTO;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use HiEvents\Services\Domain\Event\EventDeletionService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeleteSpamEventHandlerTest extends TestCase
{
    private const EVENT_ID = 1;

    private const ACCOUNT_ID = 7;

    private const REVIEWER_ID = 99;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventSpamCheckRepositoryInterface|MockInterface $eventSpamCheckRepository;

    private EventDeletionService|MockInterface $eventDeletionService;

    private AccountDeletionService|MockInterface $accountDeletionService;

    private DeleteSpamEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventSpamCheckRepository = Mockery::mock(EventSpamCheckRepositoryInterface::class);
        $this->eventDeletionService = Mockery::mock(EventDeletionService::class);
        $this->accountDeletionService = Mockery::mock(AccountDeletionService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());

        $this->handler = new DeleteSpamEventHandler(
            $this->eventRepository,
            $this->eventSpamCheckRepository,
            $this->eventDeletionService,
            $this->accountDeletionService,
            $databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_confirms_spam_and_deletes_event_without_touching_account(): void
    {
        $this->expectNotToPerformAssertions();

        $this->givenEventExists();
        $this->expectSpamCheckConfirmed();

        $this->eventDeletionService->shouldReceive('deleteEvent')->once()->with(self::EVENT_ID, self::ACCOUNT_ID);
        $this->accountDeletionService->shouldNotReceive('requestDeletion');

        $this->handler->handle($this->dto(deleteAccount: false));
    }

    public function test_deletes_event_and_requests_immediate_account_deletion(): void
    {
        $this->expectNotToPerformAssertions();

        $this->givenEventExists();
        $this->expectSpamCheckConfirmed();

        $this->eventDeletionService->shouldReceive('deleteEvent')->once()->with(self::EVENT_ID, self::ACCOUNT_ID);
        $this->accountDeletionService
            ->shouldReceive('requestDeletion')
            ->once()
            ->withArgs(fn (int $accountId, int $requestedByUserId, AccountDeletionInitiator $initiator, ?string $reason, bool $executeImmediately) => $accountId === self::ACCOUNT_ID
                && $requestedByUserId === self::REVIEWER_ID
                && $initiator === AccountDeletionInitiator::ADMIN
                && $executeImmediately)
            ->andReturn((new AccountDeletionRequestDomainObject)->setId(55));

        $this->handler->handle($this->dto(deleteAccount: true));
    }

    public function test_does_not_request_account_deletion_when_event_cannot_be_deleted(): void
    {
        $this->givenEventExists();
        $this->expectSpamCheckConfirmed();

        $this->eventDeletionService
            ->shouldReceive('deleteEvent')
            ->once()
            ->andThrow(new CannotDeleteEntityException('has completed orders'));
        $this->accountDeletionService->shouldNotReceive('requestDeletion');

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle($this->dto(deleteAccount: true));
    }

    public function test_throws_when_event_is_not_flagged_for_review(): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => self::EVENT_ID])
            ->andReturn((new EventDomainObject)->setId(self::EVENT_ID)->setAccountId(self::ACCOUNT_ID)->setStatus(EventStatus::LIVE->name));
        $this->eventSpamCheckRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->eventDeletionService->shouldNotReceive('deleteEvent');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($this->dto(deleteAccount: false));
    }

    public function test_throws_when_event_does_not_exist(): void
    {
        $this->eventRepository->shouldReceive('findFirstWhere')->with(['id' => self::EVENT_ID])->andReturnNull();
        $this->eventSpamCheckRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->eventDeletionService->shouldNotReceive('deleteEvent');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($this->dto(deleteAccount: true));
    }

    private function dto(bool $deleteAccount): DeleteSpamEventDTO
    {
        return new DeleteSpamEventDTO(
            eventId: self::EVENT_ID,
            reviewedByUserId: self::REVIEWER_ID,
            deleteAccount: $deleteAccount,
        );
    }

    private function givenEventExists(): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => self::EVENT_ID])
            ->andReturn((new EventDomainObject)
                ->setId(self::EVENT_ID)
                ->setAccountId(self::ACCOUNT_ID)
                ->setStatus(EventStatus::PENDING_MANUAL_REVIEW->name));

        $this->eventSpamCheckRepository
            ->shouldReceive('findFirstWhere')
            ->with(['event_id' => self::EVENT_ID, 'status' => EventSpamCheckStatus::FLAGGED->name])
            ->andReturn(new EventSpamCheckDomainObject);
    }

    private function expectSpamCheckConfirmed(): void
    {
        $this->eventSpamCheckRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(fn (array $attributes) => $attributes['status'] === EventSpamCheckStatus::CONFIRMED_SPAM->name
                    && $attributes['reviewed_by_user_id'] === self::REVIEWER_ID),
                ['event_id' => self::EVENT_ID, 'status' => EventSpamCheckStatus::FLAGGED->name],
            )
            ->andReturn(1);
    }
}
