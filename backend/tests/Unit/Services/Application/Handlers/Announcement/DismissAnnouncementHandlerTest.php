<?php

namespace Tests\Unit\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\DomainObjects\Status\AnnouncementStatus;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementUserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DismissAnnouncementHandler;
use HiEvents\Services\Application\Handlers\Announcement\DTO\DismissAnnouncementDTO;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DismissAnnouncementHandlerTest extends TestCase
{
    private AnnouncementRepositoryInterface|MockInterface $announcementRepository;

    private AnnouncementUserRepositoryInterface|MockInterface $announcementUserRepository;

    private DismissAnnouncementHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->announcementRepository = Mockery::mock(AnnouncementRepositoryInterface::class);
        $this->announcementUserRepository = Mockery::mock(AnnouncementUserRepositoryInterface::class);

        $this->handler = new DismissAnnouncementHandler(
            $this->announcementRepository,
            $this->announcementUserRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unpublished_or_missing_announcement_throws(): void
    {
        $this->announcementRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 5, 'status' => AnnouncementStatus::PUBLISHED->name])
            ->andReturnNull();

        $this->announcementUserRepository->shouldNotReceive('markDismissed');

        $this->expectException(AnnouncementNotFoundException::class);

        $this->handler->handle(new DismissAnnouncementDTO(announcementId: 5, userId: 9));
    }

    public function test_dismiss_marks_dismissed(): void
    {
        $this->expectNotToPerformAssertions();

        $this->announcementRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 5, 'status' => AnnouncementStatus::PUBLISHED->name])
            ->andReturn(new AnnouncementDomainObject);

        $this->announcementUserRepository
            ->shouldReceive('markDismissed')
            ->once()
            ->with(5, 9);

        $this->handler->handle(new DismissAnnouncementDTO(announcementId: 5, userId: 9));
    }
}
