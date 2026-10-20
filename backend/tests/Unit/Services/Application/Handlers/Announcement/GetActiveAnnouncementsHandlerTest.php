<?php

namespace Tests\Unit\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\DomainObjects\Enums\AnnouncementDisplayType;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementUserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\GetActiveAnnouncementsDTO;
use HiEvents\Services\Application\Handlers\Announcement\GetActiveAnnouncementsHandler;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetActiveAnnouncementsHandlerTest extends TestCase
{
    private AnnouncementRepositoryInterface|MockInterface $announcementRepository;

    private AnnouncementUserRepositoryInterface|MockInterface $announcementUserRepository;

    private GetActiveAnnouncementsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->announcementRepository = Mockery::mock(AnnouncementRepositoryInterface::class);
        $this->announcementUserRepository = Mockery::mock(AnnouncementUserRepositoryInterface::class);

        $this->handler = new GetActiveAnnouncementsHandler(
            $this->announcementRepository,
            $this->announcementUserRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeAnnouncement(int $id, AnnouncementDisplayType $displayType): AnnouncementDomainObject
    {
        return (new AnnouncementDomainObject)
            ->setId($id)
            ->setDisplayType($displayType->name);
    }

    public function test_newest_banner_and_modal_are_returned_and_marked_seen(): void
    {
        $this->announcementRepository
            ->shouldReceive('findActiveForUser')
            ->once()
            ->with(7, 3)
            ->andReturn(new Collection([
                $this->makeAnnouncement(30, AnnouncementDisplayType::MODAL),
                $this->makeAnnouncement(20, AnnouncementDisplayType::BANNER),
                $this->makeAnnouncement(10, AnnouncementDisplayType::BANNER),
            ]));

        $this->announcementUserRepository
            ->shouldReceive('markSeen')
            ->once()
            ->with([20, 30], 7);

        $result = $this->handler->handle(new GetActiveAnnouncementsDTO(userId: 7, accountId: 3));

        $this->assertCount(2, $result);
        $this->assertSame([20, 30], $result->map(fn (AnnouncementDomainObject $a) => $a->getId())->all());
    }

    public function test_empty_result_skips_mark_seen(): void
    {
        $this->announcementRepository
            ->shouldReceive('findActiveForUser')
            ->once()
            ->with(7, 3)
            ->andReturn(new Collection);

        $this->announcementUserRepository->shouldNotReceive('markSeen');

        $result = $this->handler->handle(new GetActiveAnnouncementsDTO(userId: 7, accountId: 3));

        $this->assertTrue($result->isEmpty());
    }
}
