<?php

namespace Tests\Unit\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\DomainObjects\Enums\AnnouncementDisplayType;
use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\Generated\AnnouncementDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AnnouncementStatus;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\AnnouncementPayloadNormaliser;
use HiEvents\Services\Application\Handlers\Announcement\DTO\UpsertAnnouncementDTO;
use HiEvents\Services\Application\Handlers\Announcement\UpdateAnnouncementHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateAnnouncementHandlerTest extends TestCase
{
    private AnnouncementRepositoryInterface|MockInterface $announcementRepository;

    private HtmlPurifierService|MockInterface $purifier;

    private UpdateAnnouncementHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->announcementRepository = Mockery::mock(AnnouncementRepositoryInterface::class);
        $this->purifier = Mockery::mock(HtmlPurifierService::class);

        $this->handler = new UpdateAnnouncementHandler(
            $this->announcementRepository,
            new AnnouncementPayloadNormaliser($this->purifier),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDto(): UpsertAnnouncementDTO
    {
        return new UpsertAnnouncementDTO(
            title: 'Updated',
            content: '<p>Body</p>',
            status: AnnouncementStatus::PUBLISHED->name,
            displayType: AnnouncementDisplayType::MODAL->name,
            targetType: AnnouncementTargetType::ALL->name,
            emoji: '🚀',
            announcementId: 44,
        );
    }

    public function test_missing_announcement_throws(): void
    {
        $this->announcementRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 44])
            ->andReturnNull();

        $this->expectException(AnnouncementNotFoundException::class);

        $this->handler->handle($this->makeDto());
    }

    public function test_update_purifies_modal_content(): void
    {
        $this->announcementRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['id' => 44])
            ->andReturn(new AnnouncementDomainObject);

        $this->purifier
            ->shouldReceive('purify')
            ->once()
            ->with('<p>Body</p>')
            ->andReturn('<p>Body</p>');

        $this->announcementRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(44, Mockery::on(function (array $attributes) {
                return $attributes[AnnouncementDomainObjectAbstract::CONTENT] === '<p>Body</p>'
                    && $attributes[AnnouncementDomainObjectAbstract::STATUS] === AnnouncementStatus::PUBLISHED->name;
            }))
            ->andReturn(new AnnouncementDomainObject);

        $this->assertInstanceOf(AnnouncementDomainObject::class, $this->handler->handle($this->makeDto()));
    }
}
