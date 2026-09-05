<?php

namespace Tests\Unit\Services\Domain\Account;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\Enums\AccountDeletionOutcome;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\AccountDeletionRequestNotFoundException;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Mail\Account\AccountDeletionCancelledEmail;
use HiEvents\Mail\Account\AccountDeletionCompletedEmail;
use HiEvents\Mail\Account\AccountDeletionRequestedEmail;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountAnonymizationService;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use HiEvents\Services\Domain\Account\AccountHardDeletionService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    private AccountDeletionService $service;

    private AccountDeletionRequestRepositoryInterface|MockInterface $deletionRequestRepository;

    private AccountRepositoryInterface|MockInterface $accountRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private AccountAnonymizationService|MockInterface $anonymizationService;

    private AccountHardDeletionService|MockInterface $hardDeletionService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->deletionRequestRepository = Mockery::mock(AccountDeletionRequestRepositoryInterface::class);
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->anonymizationService = Mockery::mock(AccountAnonymizationService::class);
        $this->hardDeletionService = Mockery::mock(AccountHardDeletionService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->service = new AccountDeletionService(
            $this->deletionRequestRepository,
            $this->accountRepository,
            $this->eventRepository,
            $this->orderRepository,
            $this->anonymizationService,
            $this->hardDeletionService,
            $databaseManager,
            new NullLogger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cannot_delete_reason_is_null_when_no_blockers_exist(): void
    {
        $this->mockNoActiveRequest();
        $this->mockUpcomingEventsWithCompletedOrders([]);

        $this->assertNull($this->service->getCannotDeleteReason(1));
    }

    public function test_cannot_delete_when_request_already_exists(): void
    {
        $this->mockActiveRequest();

        $this->assertSame(
            'Account deletion has already been requested.',
            $this->service->getCannotDeleteReason(1),
        );
    }

    public function test_cannot_delete_when_upcoming_events_have_completed_orders(): void
    {
        $this->mockNoActiveRequest();
        $this->mockUpcomingEventsWithCompletedOrders(['Summer Festival', 'Winter Gala']);

        $reason = $this->service->getCannotDeleteReason(1);

        $this->assertStringContainsString('Summer Festival, Winter Gala', $reason);
    }

    public function test_outcome_is_hard_delete_when_account_has_no_completed_orders(): void
    {
        $this->orderRepository->shouldReceive('accountHasCompletedOrders')->with(1)->andReturnFalse();

        $this->assertSame(AccountDeletionOutcome::HARD_DELETE, $this->service->determineOutcome(1));
    }

    public function test_outcome_is_anonymize_when_account_has_completed_orders(): void
    {
        $this->orderRepository->shouldReceive('accountHasCompletedOrders')->with(1)->andReturnTrue();

        $this->assertSame(AccountDeletionOutcome::ANONYMIZE, $this->service->determineOutcome(1));
    }

    public function test_request_deletion_creates_request_and_unpublishes_events(): void
    {
        $this->mockNoActiveRequest();
        $this->mockUpcomingEventsWithCompletedOrders([]);
        $this->orderRepository->shouldReceive('accountHasCompletedOrders')->andReturnFalse();
        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($this->makeAccount());

        $this->deletionRequestRepository->shouldReceive('create')
            ->once()
            ->withArgs(function (array $attributes) {
                return $attributes['account_id'] === 1
                    && $attributes['requested_by_user_id'] === 5
                    && $attributes['initiated_by'] === AccountDeletionInitiator::ACCOUNT_OWNER->name
                    && $attributes['status'] === AccountDeletionRequestStatus::REQUESTED->name
                    && $attributes['expected_outcome'] === AccountDeletionOutcome::HARD_DELETE->name;
            })
            ->andReturn($this->makeDeletionRequest());

        $this->eventRepository->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => EventStatus::DRAFT->name],
                ['account_id' => 1, ['status', 'in', [EventStatus::LIVE->name, EventStatus::PENDING_MANUAL_REVIEW->name]]],
            )
            ->andReturn(1);

        $result = $this->service->requestDeletion(1, 5, AccountDeletionInitiator::ACCOUNT_OWNER);

        $this->assertInstanceOf(AccountDeletionRequestDomainObject::class, $result);
        Mail::assertQueued(AccountDeletionRequestedEmail::class);
    }

    public function test_request_deletion_throws_when_gated(): void
    {
        $this->mockActiveRequest();

        $this->expectException(CannotDeleteEntityException::class);

        $this->service->requestDeletion(1, 5, AccountDeletionInitiator::ACCOUNT_OWNER);
    }

    public function test_cancel_deletion_updates_request_and_sends_email(): void
    {
        $this->mockActiveRequest();
        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($this->makeAccount());

        $this->deletionRequestRepository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(function (int $id, array $attributes) {
                return $id === 10
                    && $attributes['status'] === AccountDeletionRequestStatus::CANCELLED->name
                    && $attributes['cancelled_by_user_id'] === 5;
            })
            ->andReturn($this->makeDeletionRequest(AccountDeletionRequestStatus::CANCELLED));

        $result = $this->service->cancelDeletion(1, 5);

        $this->assertSame(AccountDeletionRequestStatus::CANCELLED->name, $result->getStatus());
        Mail::assertQueued(AccountDeletionCancelledEmail::class);
    }

    public function test_cancel_deletion_throws_when_no_active_request(): void
    {
        $this->mockNoActiveRequest();

        $this->expectException(AccountDeletionRequestNotFoundException::class);

        $this->service->cancelDeletion(1, 5);
    }

    public function test_execute_deletion_skips_inactive_requests(): void
    {
        $this->deletionRequestRepository->shouldReceive('findById')
            ->with(10)
            ->andReturn($this->makeDeletionRequest(AccountDeletionRequestStatus::CANCELLED));

        $this->hardDeletionService->shouldNotReceive('deleteAccount');
        $this->anonymizationService->shouldNotReceive('anonymizeAccount');

        $this->service->executeDeletion(10);

        Mail::assertNothingQueued();
    }

    public function test_execute_deletion_hard_deletes_when_no_completed_orders(): void
    {
        $this->deletionRequestRepository->shouldReceive('findById')
            ->with(10)
            ->andReturn($this->makeDeletionRequest());
        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($this->makeAccount());
        $this->orderRepository->shouldReceive('accountHasCompletedOrders')->with(1)->andReturnFalse();

        $this->hardDeletionService->shouldReceive('deleteAccount')->with(1)->once()->andReturn(['accounts' => 1]);
        $this->anonymizationService->shouldNotReceive('anonymizeAccount');

        $this->deletionRequestRepository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(function (int $id, array $attributes) {
                return $id === 10
                    && $attributes['status'] === AccountDeletionRequestStatus::COMPLETED->name
                    && $attributes['outcome'] === AccountDeletionOutcome::HARD_DELETE->name
                    && $attributes['deletion_manifest'] === ['accounts' => 1];
            })
            ->andReturn($this->makeDeletionRequest(AccountDeletionRequestStatus::COMPLETED));

        $this->service->executeDeletion(10);

        Mail::assertQueued(AccountDeletionCompletedEmail::class);
    }

    public function test_execute_deletion_anonymizes_when_completed_orders_exist(): void
    {
        $this->deletionRequestRepository->shouldReceive('findById')
            ->with(10)
            ->andReturn($this->makeDeletionRequest());
        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($this->makeAccount());
        $this->orderRepository->shouldReceive('accountHasCompletedOrders')->with(1)->andReturnTrue();

        $this->anonymizationService->shouldReceive('anonymizeAccount')->with(1)->once()->andReturn([]);
        $this->hardDeletionService->shouldNotReceive('deleteAccount');

        $this->deletionRequestRepository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes['outcome'] === AccountDeletionOutcome::ANONYMIZE->name)
            ->andReturn($this->makeDeletionRequest(AccountDeletionRequestStatus::COMPLETED));

        $this->service->executeDeletion(10);

        Mail::assertQueued(AccountDeletionCompletedEmail::class);
    }

    private function mockNoActiveRequest(): void
    {
        $this->deletionRequestRepository->shouldReceive('findFirstWhere')->andReturnNull();
    }

    private function mockActiveRequest(): void
    {
        $this->deletionRequestRepository->shouldReceive('findFirstWhere')
            ->andReturn($this->makeDeletionRequest());
    }

    private function mockUpcomingEventsWithCompletedOrders(array $titles): void
    {
        $this->eventRepository->shouldReceive('getUpcomingEventsWithCompletedOrders')
            ->andReturn(collect($titles)->map(function (string $title) {
                $event = new EventDomainObject;
                $event->setTitle($title);

                return $event;
            }));
    }

    private function makeDeletionRequest(
        AccountDeletionRequestStatus $status = AccountDeletionRequestStatus::REQUESTED,
    ): AccountDeletionRequestDomainObject {
        $request = new AccountDeletionRequestDomainObject;
        $request->setId(10);
        $request->setAccountId(1);
        $request->setStatus($status->name);
        $request->setExpectedOutcome(AccountDeletionOutcome::HARD_DELETE->name);
        $request->setScheduledDeletionAt(now()->addDays(30)->toDateTimeString());

        return $request;
    }

    private function makeAccount(): AccountDomainObject
    {
        $account = new AccountDomainObject;
        $account->setId(1);
        $account->setName('Test Account');
        $account->setEmail('owner@example.com');
        $account->setTimezone('UTC');

        return $account;
    }
}
