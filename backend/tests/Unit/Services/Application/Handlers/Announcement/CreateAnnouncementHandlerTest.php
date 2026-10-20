<?php

namespace Tests\Unit\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\DomainObjects\Enums\AnnouncementDisplayType;
use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\Generated\AnnouncementDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AnnouncementStatus;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\AnnouncementPayloadNormaliser;
use HiEvents\Services\Application\Handlers\Announcement\CreateAnnouncementHandler;
use HiEvents\Services\Application\Handlers\Announcement\DTO\UpsertAnnouncementDTO;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateAnnouncementHandlerTest extends TestCase
{
    private AnnouncementRepositoryInterface|MockInterface $announcementRepository;

    private HtmlPurifierService|MockInterface $purifier;

    private CreateAnnouncementHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->announcementRepository = Mockery::mock(AnnouncementRepositoryInterface::class);
        $this->purifier = Mockery::mock(HtmlPurifierService::class);

        $this->handler = new CreateAnnouncementHandler(
            $this->announcementRepository,
            new AnnouncementPayloadNormaliser($this->purifier),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_modal_content_is_purified_and_emoji_kept(): void
    {
        $dto = new UpsertAnnouncementDTO(
            title: 'New feature',
            content: '<p>Hello <script>alert(1)</script></p>',
            status: AnnouncementStatus::PUBLISHED->name,
            displayType: AnnouncementDisplayType::MODAL->name,
            targetType: AnnouncementTargetType::ALL->name,
            emoji: '🎉',
        );

        $this->purifier
            ->shouldReceive('purify')
            ->once()
            ->with($dto->content)
            ->andReturn('<p>Hello </p>');

        $this->announcementRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attributes) {
                return $attributes[AnnouncementDomainObjectAbstract::CONTENT] === '<p>Hello </p>'
                    && $attributes[AnnouncementDomainObjectAbstract::EMOJI] === '🎉'
                    && $attributes[AnnouncementDomainObjectAbstract::TARGET_ACCOUNT_IDS] === null
                    && $attributes[AnnouncementDomainObjectAbstract::TARGET_USER_IDS] === null;
            }))
            ->andReturn(new AnnouncementDomainObject);

        $this->assertInstanceOf(AnnouncementDomainObject::class, $this->handler->handle($dto));
    }

    public function test_banner_content_is_kept_raw_and_emoji_discarded(): void
    {
        $dto = new UpsertAnnouncementDTO(
            title: 'Maintenance',
            content: 'Prices < $100 this Saturday',
            status: AnnouncementStatus::PUBLISHED->name,
            displayType: AnnouncementDisplayType::BANNER->name,
            targetType: AnnouncementTargetType::ALL->name,
            emoji: '🎉',
            ctaLabel: 'Learn more',
            ctaUrl: 'https://hi.events/changelog',
        );

        $this->purifier->shouldNotReceive('purify');

        $this->announcementRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attributes) {
                return $attributes[AnnouncementDomainObjectAbstract::CONTENT] === 'Prices < $100 this Saturday'
                    && $attributes[AnnouncementDomainObjectAbstract::EMOJI] === null
                    && $attributes[AnnouncementDomainObjectAbstract::CTA_LABEL] === 'Learn more'
                    && $attributes[AnnouncementDomainObjectAbstract::CTA_URL] === 'https://hi.events/changelog';
            }))
            ->andReturn(new AnnouncementDomainObject);

        $this->assertInstanceOf(AnnouncementDomainObject::class, $this->handler->handle($dto));
    }

    public function test_target_ids_are_int_cast_and_mismatched_target_list_is_nulled(): void
    {
        $dto = new UpsertAnnouncementDTO(
            title: 'For some accounts',
            content: 'Hello',
            status: AnnouncementStatus::DRAFT->name,
            displayType: AnnouncementDisplayType::BANNER->name,
            targetType: AnnouncementTargetType::ACCOUNTS->name,
            targetAccountIds: ['5', 7],
            targetUserIds: [9],
        );

        $this->announcementRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $attributes) {
                return $attributes[AnnouncementDomainObjectAbstract::TARGET_ACCOUNT_IDS] === [5, 7]
                    && $attributes[AnnouncementDomainObjectAbstract::TARGET_USER_IDS] === null;
            }))
            ->andReturn(new AnnouncementDomainObject);

        $this->assertInstanceOf(AnnouncementDomainObject::class, $this->handler->handle($dto));
    }
}
