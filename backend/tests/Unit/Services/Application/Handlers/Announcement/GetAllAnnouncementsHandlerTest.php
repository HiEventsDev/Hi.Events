<?php

namespace Tests\Unit\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\Announcement;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\GetAllAnnouncementsDTO;
use HiEvents\Services\Application\Handlers\Announcement\GetAllAnnouncementsHandler;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetAllAnnouncementsHandlerTest extends TestCase
{
    private AnnouncementRepositoryInterface|MockInterface $announcementRepository;

    private AccountRepositoryInterface|MockInterface $accountRepository;

    private UserRepositoryInterface|MockInterface $userRepository;

    private GetAllAnnouncementsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->announcementRepository = Mockery::mock(AnnouncementRepositoryInterface::class);
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);

        $this->handler = new GetAllAnnouncementsHandler(
            $this->announcementRepository,
            $this->accountRepository,
            $this->userRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeAnnouncement(array $attributes): Announcement
    {
        return (new Announcement)->setRawAttributes($attributes);
    }

    public function test_target_names_are_resolved_with_fallbacks_and_deactivated_suffix(): void
    {
        $accountsAnnouncement = $this->makeAnnouncement([
            'id' => 1,
            'target_type' => 'ACCOUNTS',
            'target_account_ids' => json_encode([5, 999]),
        ]);
        $usersAnnouncement = $this->makeAnnouncement([
            'id' => 2,
            'target_type' => 'USERS',
            'target_user_ids' => json_encode([7]),
        ]);
        $allAnnouncement = $this->makeAnnouncement([
            'id' => 3,
            'target_type' => 'ALL',
        ]);

        $this->announcementRepository
            ->shouldReceive('getAnnouncementsWithCounts')
            ->once()
            ->with('promo', 20)
            ->andReturn(new LengthAwarePaginator(
                [$accountsAnnouncement, $usersAnnouncement, $allAnnouncement],
                3,
                20,
            ));

        $this->accountRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->with('id', [5, 999])
            ->andReturn(new Collection([
                (new AccountDomainObject)->setId(5)->setName('Acme Events'),
            ]));

        $this->userRepository->shouldReceive('includeDeleted')->once()->andReturnSelf();
        $this->userRepository
            ->shouldReceive('findWhereIn')
            ->once()
            ->with('id', [7])
            ->andReturn(new Collection([
                (new UserDomainObject)
                    ->setId(7)
                    ->setFirstName('Jane')
                    ->setLastName('Doe')
                    ->setDeletedAt('2026-01-01 00:00:00'),
            ]));

        $this->handler->handle(new GetAllAnnouncementsDTO(perPage: 20, search: 'promo'));

        $this->assertSame([5 => 'Acme Events', 999 => '#999'], $accountsAnnouncement->target_names);
        $this->assertSame([7 => 'Jane Doe (deactivated)'], $usersAnnouncement->target_names);
        $this->assertSame([], $allAnnouncement->target_names);
    }
}
