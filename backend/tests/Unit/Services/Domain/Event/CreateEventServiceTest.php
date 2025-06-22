<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Event\CreateEventService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;
use Tests\TestCase;

class CreateEventServiceTest extends TestCase
{
    private CreateEventService $createEventService;
    private EventRepositoryInterface $eventRepository;
    private EventSettingsRepositoryInterface $eventSettingsRepository;
    private OrganizerRepositoryInterface $organizerRepository;
    private DatabaseManager $databaseManager;
    private EventStatisticRepositoryInterface $eventStatisticsRepository;
    private HtmlPurifierService $purifier;
    private ImageRepositoryInterface $imageRepository;
    private Repository $config;
    private FilesystemManager $filesystemManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->purifier = Mockery::mock(HtmlPurifierService::class);
        $this->imageRepository = Mockery::mock(ImageRepositoryInterface::class);
        $this->config = Mockery::mock(Repository::class);
        $this->filesystemManager = Mockery::mock(FilesystemManager::class);

        $this->createEventService = new CreateEventService(
            $this->eventRepository,
            $this->eventSettingsRepository,
            $this->organizerRepository,
            $this->databaseManager,
            $this->eventStatisticsRepository,
            $this->purifier,
            $this->imageRepository,
            $this->config,
            $this->filesystemManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateEventSuccess(): void
    {
        $eventData = $this->createMockEventDomainObject();
        $eventSettings = $this->createMockEventSettingDomainObject();
        $organizer = $this->createMockOrganizerDomainObject();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->organizerRepository
            ->shouldReceive('loadRelation')
            ->with(OrganizerSettingDomainObject::class)
            ->once()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findFirstWhere')
            ->with([
                'id' => $eventData->getOrganizerId(),
                'account_id' => $eventData->getAccountId(),
            ])
            ->andReturn($organizer);

        $this->eventRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['title'] === $eventData->getTitle() &&
                    $arg['organizer_id'] === $eventData->getOrganizerId() &&
                    $arg['account_id'] === $eventData->getAccountId() &&
                    $arg['category'] === $eventData->getCategory();
            }))
            ->andReturn($eventData);

        $this->eventSettingsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['event_id'] === $eventData->getId();
            }));

        $this->eventStatisticsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['event_id'] === $eventData->getId() &&
                    $arg['products_sold'] === 0 &&
                    $arg['sales_total_gross'] === 0;
            }));

        // Mock event cover creation
        $this->config->shouldReceive('get')
            ->with('filesystems.public')
            ->andReturn('public');
        $this->config->shouldReceive('get')
            ->with('app.event_categories_cover_images_path')
            ->andReturn('event-covers');
        $this->config->shouldReceive('get')
            ->with('filesystems.default')
            ->andReturn('local');
        
        $mockDisk = Mockery::mock();
        $mockDisk->shouldReceive('exists')
            ->with('event-covers/CONFERENCE.jpg')
            ->andReturn(true);
        
        $this->filesystemManager->shouldReceive('disk')
            ->with('public')
            ->andReturn($mockDisk);
            
        $this->imageRepository->shouldReceive('create')
            ->once();

        $this->purifier->shouldReceive('purify')->andReturn('Test Description');

        $result = $this->createEventService->createEvent($eventData, $eventSettings);

        $this->assertEquals($eventData->getId(), $result->getId());
    }

    public function testCreateEventWithoutEventSettings(): void
    {
        $eventData = $this->createMockEventDomainObject();
        $organizer = $this->createMockOrganizerDomainObject()
            ->shouldReceive('getOrganizerSettings')
            ->andReturn(new OrganizerSettingDomainObject())
            ->getMock();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->organizerRepository
            ->shouldReceive('loadRelation')
            ->with(OrganizerSettingDomainObject::class)
            ->once()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findFirstWhere')
            ->andReturn($organizer);

        $this->eventRepository->shouldReceive('create')->andReturn($eventData);

        $this->purifier->shouldReceive('purify')->andReturn('Test Description');

        $this->eventSettingsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData, $organizer) {
                return $arg['event_id'] === $eventData->getId() &&
                    $arg['homepage_background_type'] === HomepageBackgroundType::COLOR->name &&
                    $arg['support_email'] === $organizer->getEmail();
            }));

        $this->eventStatisticsRepository->shouldReceive('create');

        // Mock event cover creation
        $this->config->shouldReceive('get')
            ->with('filesystems.public')
            ->andReturn('public');
        $this->config->shouldReceive('get')
            ->with('app.event_categories_cover_images_path')
            ->andReturn('event-covers');
        $this->config->shouldReceive('get')
            ->with('filesystems.default')
            ->andReturn('local');
        
        $mockDisk = Mockery::mock();
        $mockDisk->shouldReceive('exists')
            ->with('event-covers/CONFERENCE.jpg')
            ->andReturn(false); // No cover exists for this test
        
        $this->filesystemManager->shouldReceive('disk')
            ->with('public')
            ->andReturn($mockDisk);

        $this->createEventService->createEvent($eventData);

        $this->assertTrue(true);
    }

    public function testCreateEventThrowsOrganizerNotFoundException(): void
    {
        $eventData = $this->createMockEventDomainObject();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->organizerRepository
            ->shouldReceive('loadRelation')
            ->with(OrganizerSettingDomainObject::class)
            ->once()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findFirstWhere')
            ->andReturnNull();

        $this->expectException(OrganizerNotFoundException::class);

        $this->createEventService->createEvent($eventData);
    }

    public function testCreateEventWithEventCoverCreatesImageRecord(): void
    {
        $eventData = $this->createMockEventDomainObject();
        $organizer = $this->createMockOrganizerDomainObject()
            ->shouldReceive('getOrganizerSettings')
            ->andReturn(new OrganizerSettingDomainObject())
            ->getMock();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->organizerRepository
            ->shouldReceive('loadRelation')
            ->with(OrganizerSettingDomainObject::class)
            ->once()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findFirstWhere')
            ->andReturn($organizer);

        $this->eventRepository->shouldReceive('create')->andReturn($eventData);

        // Mock that cover image exists
        $this->config->shouldReceive('get')
            ->with('filesystems.public')
            ->andReturn('public');
        $this->config->shouldReceive('get')
            ->with('app.event_categories_cover_images_path')
            ->andReturn('event-covers');
        $this->config->shouldReceive('get')
            ->with('filesystems.default')
            ->andReturn('local');
        
        $mockDisk = Mockery::mock();
        $mockDisk->shouldReceive('exists')
            ->with('event-covers/CONFERENCE.jpg')
            ->andReturn(true);
        
        $this->filesystemManager->shouldReceive('disk')
            ->with('public')
            ->andReturn($mockDisk);
            
        // Verify image record is created with correct data
        $this->imageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['account_id'] === $eventData->getAccountId() &&
                    $arg['entity_id'] === $eventData->getId() &&
                    $arg['entity_type'] === EventDomainObject::class &&
                    $arg['type'] === ImageType::EVENT_COVER->name &&
                    $arg['filename'] === 'CONFERENCE.jpg' &&
                    $arg['path'] === 'event-covers/CONFERENCE.jpg';
            }));

        $this->eventSettingsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) {
                // When cover is created, background type should be MIRROR_COVER_IMAGE
                return $arg['homepage_background_type'] === HomepageBackgroundType::MIRROR_COVER_IMAGE->name;
            }));

        $this->eventStatisticsRepository->shouldReceive('create');

        $this->purifier->shouldReceive('purify')->andReturn('Test Description');

        $this->createEventService->createEvent($eventData);

        $this->assertTrue(true);
    }

    public function testCreateEventWithoutEventCoverDoesNotCreateImageRecord(): void
    {
        $eventData = $this->createMockEventDomainObjectWithCategory('MUSIC');
        $organizer = $this->createMockOrganizerDomainObject()
            ->shouldReceive('getOrganizerSettings')
            ->andReturn(new OrganizerSettingDomainObject())
            ->getMock();

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->organizerRepository
            ->shouldReceive('loadRelation')
            ->with(OrganizerSettingDomainObject::class)
            ->once()
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('findFirstWhere')
            ->andReturn($organizer);

        $this->eventRepository->shouldReceive('create')->andReturn($eventData);

        // Mock that cover image does not exist for MUSIC category
        $this->config->shouldReceive('get')
            ->with('filesystems.public')
            ->andReturn('public');
        $this->config->shouldReceive('get')
            ->with('app.event_categories_cover_images_path')
            ->andReturn('event-covers');
        
        $mockDisk = Mockery::mock();
        $mockDisk->shouldReceive('exists')
            ->with('event-covers/MUSIC.jpg')
            ->andReturn(false);
        
        $this->filesystemManager->shouldReceive('disk')
            ->with('public')
            ->andReturn($mockDisk);
            
        // Image repository should NOT be called
        $this->imageRepository->shouldNotReceive('create');

        $this->eventSettingsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) {
                // When no cover is created, background type should be COLOR
                return $arg['homepage_background_type'] === HomepageBackgroundType::COLOR->name;
            }));

        $this->eventStatisticsRepository->shouldReceive('create');

        $this->purifier->shouldReceive('purify')->andReturn('Test Description');

        $this->createEventService->createEvent($eventData);

        $this->assertTrue(true);
    }

    private function createMockEventDomainObject(): EventDomainObject
    {
        return Mockery::mock(EventDomainObject::class, static function ($mock) {
            $mock->shouldReceive('getId')->andReturn(1);
            $mock->shouldReceive('getTitle')->andReturn('Test Event');
            $mock->shouldReceive('getOrganizerId')->andReturn(1);
            $mock->shouldReceive('getAccountId')->andReturn(1);
            $mock->shouldReceive('getStartDate')->andReturn('2023-01-01 00:00:00');
            $mock->shouldReceive('getEndDate')->andReturn('2023-01-02 00:00:00');
            $mock->shouldReceive('getTimezone')->andReturn('UTC');
            $mock->shouldReceive('getCurrency')->andReturn('USD');
            $mock->shouldReceive('getDescription')->andReturn('Test Description');
            $mock->shouldReceive('getLocationDetails')->andReturn('Test Location');
            $mock->shouldReceive('getUserId')->andReturn(1);
            $mock->shouldReceive('getStatus')->andReturn('active');
            $mock->shouldReceive('getCategory')->andReturn('CONFERENCE');
            $mock->shouldReceive('getAttributes')->andReturn([]);
        });
    }

    private function createMockEventSettingDomainObject(): EventSettingDomainObject
    {
        return Mockery::mock(EventSettingDomainObject::class, function ($mock) {
            $mock->shouldReceive('setEventId');
            $mock->shouldReceive('toArray')->andReturn([
                'event_id' => 1,
                'homepage_background_color' => '#ffffff',
            ]);
        });
    }

    private function createMockOrganizerDomainObject(): OrganizerDomainObject|Mockery\Mock
    {
        return Mockery::mock(OrganizerDomainObject::class, function ($mock) {
            $mock->shouldReceive('getEmail')->andReturn('organizer@example.com');
            $mock->shouldReceive('getName')->andReturn('Organizer Name');
        });
    }

    private function createMockEventDomainObjectWithCategory(string $category): EventDomainObject
    {
        return Mockery::mock(EventDomainObject::class, static function ($mock) use ($category) {
            $mock->shouldReceive('getId')->andReturn(1);
            $mock->shouldReceive('getTitle')->andReturn('Test Event');
            $mock->shouldReceive('getOrganizerId')->andReturn(1);
            $mock->shouldReceive('getAccountId')->andReturn(1);
            $mock->shouldReceive('getStartDate')->andReturn('2023-01-01 00:00:00');
            $mock->shouldReceive('getEndDate')->andReturn('2023-01-02 00:00:00');
            $mock->shouldReceive('getTimezone')->andReturn('UTC');
            $mock->shouldReceive('getCurrency')->andReturn('USD');
            $mock->shouldReceive('getDescription')->andReturn('Test Description');
            $mock->shouldReceive('getLocationDetails')->andReturn('Test Location');
            $mock->shouldReceive('getUserId')->andReturn(1);
            $mock->shouldReceive('getStatus')->andReturn('active');
            $mock->shouldReceive('getCategory')->andReturn($category);
            $mock->shouldReceive('getAttributes')->andReturn([]);
        });
    }
}
