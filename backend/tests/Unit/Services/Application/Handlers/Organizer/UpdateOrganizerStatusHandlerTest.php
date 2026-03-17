<?php

namespace Tests\Unit\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerStatusDTO;
use HiEvents\Services\Application\Handlers\Organizer\UpdateOrganizerStatusHandler;
use Illuminate\Database\DatabaseManager;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class UpdateOrganizerStatusHandlerTest extends TestCase
{
    private OrganizerRepositoryInterface $organizerRepository;
    private AccountRepositoryInterface $accountRepository;
    private EventRepositoryInterface $eventRepository;
    private UpdateOrganizerStatusHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizerRepository = m::mock(OrganizerRepositoryInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $logger = m::mock(LoggerInterface::class);
        $databaseManager = m::mock(DatabaseManager::class);

        $databaseManager->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $logger->shouldReceive('info')->byDefault();

        $this->handler = new UpdateOrganizerStatusHandler(
            $this->organizerRepository,
            $this->accountRepository,
            $this->eventRepository,
            $logger,
            $databaseManager,
        );
    }

    public function testArchiveLastActiveOrganizerFails(): void
    {
        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn('2024-01-01');

        $this->accountRepository->shouldReceive('findById')
            ->with(10)
            ->andReturn($account);

        $this->organizerRepository->shouldReceive('countWhere')
            ->with([
                'account_id' => 10,
                ['status', '!=', OrganizerStatus::ARCHIVED->name],
            ])
            ->andReturn(1);

        $dto = new UpdateOrganizerStatusDTO(
            status: OrganizerStatus::ARCHIVED->name,
            organizerId: 1,
            accountId: 10,
        );

        $this->expectException(CannotDeleteEntityException::class);

        $this->handler->handle($dto);
    }

    public function testArchiveOrganizerSucceedsWhenOtherActiveOrganizersExist(): void
    {
        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn('2024-01-01');

        $this->accountRepository->shouldReceive('findById')
            ->with(10)
            ->andReturn($account);

        $this->organizerRepository->shouldReceive('countWhere')
            ->with([
                'account_id' => 10,
                ['status', '!=', OrganizerStatus::ARCHIVED->name],
            ])
            ->andReturn(3);

        $this->organizerRepository->shouldReceive('updateWhere')
            ->once()
            ->with(
                m::on(fn($attrs) => $attrs['status'] === OrganizerStatus::ARCHIVED->name),
                m::on(fn($where) => $where['id'] === 1 && $where['account_id'] === 10),
            )
            ->andReturn(1);

        $this->eventRepository->shouldReceive('updateWhere')
            ->once()
            ->andReturn(1);

        $updatedOrganizer = m::mock(OrganizerDomainObject::class);
        $this->organizerRepository->shouldReceive('findFirstWhere')
            ->with(['id' => 1, 'account_id' => 10])
            ->andReturn($updatedOrganizer);

        $dto = new UpdateOrganizerStatusDTO(
            status: OrganizerStatus::ARCHIVED->name,
            organizerId: 1,
            accountId: 10,
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($updatedOrganizer, $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
