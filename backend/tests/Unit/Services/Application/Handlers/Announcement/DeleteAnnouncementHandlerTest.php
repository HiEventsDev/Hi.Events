<?php

namespace Tests\Unit\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DeleteAnnouncementHandler;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeleteAnnouncementHandlerTest extends TestCase
{
    private AnnouncementRepositoryInterface|MockInterface $announcementRepository;

    private DeleteAnnouncementHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->announcementRepository = Mockery::mock(AnnouncementRepositoryInterface::class);
        $this->handler = new DeleteAnnouncementHandler($this->announcementRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_missing_announcement_throws(): void
    {
        $this->announcementRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 77])
            ->andReturnNull();

        $this->announcementRepository->shouldNotReceive('deleteById');

        $this->expectException(AnnouncementNotFoundException::class);

        $this->handler->handle(77);
    }

    public function test_delete_soft_deletes(): void
    {
        $this->expectNotToPerformAssertions();

        $this->announcementRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 77])
            ->andReturn(new AnnouncementDomainObject);

        $this->announcementRepository
            ->shouldReceive('deleteById')
            ->once()
            ->with(77);

        $this->handler->handle(77);
    }
}
