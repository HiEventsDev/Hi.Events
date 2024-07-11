<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Event\CreateEventService;
use HTMLPurifier;
use Illuminate\Database\DatabaseManager;
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
    private HTMLPurifier $purifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->eventStatisticsRepository = Mockery::mock(EventStatisticRepositoryInterface::class);
        $this->purifier = Mockery::mock(HTMLPurifier::class);

        $this->createEventService = new CreateEventService(
            $this->eventRepository,
            $this->eventSettingsRepository,
            $this->organizerRepository,
            $this->databaseManager,
            $this->eventStatisticsRepository,
            $this->purifier,
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

        $this->databaseManager->shouldReceive('beginTransaction')->once();
        $this->databaseManager->shouldReceive('commit')->once();

        $this->organizerRepository->shouldReceive('findFirstWhere')
            ->with([
                'id' => $eventData->getOrganizerId(),
                'account_id' => $eventData->getAccountId(),
            ])
            ->andReturn($organizer);

        $this->eventRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['title'] === $eventData->getTitle() &&
                    $arg['organizer_id'] === $eventData->getOrganizerId() &&
                    $arg['account_id'] === $eventData->getAccountId();
            }))
            ->andReturn($eventData);

        $this->eventSettingsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['event_id'] === $eventData->getId();
            }));

        $this->eventStatisticsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData) {
                return $arg['event_id'] === $eventData->getId() &&
                    $arg['tickets_sold'] === 0 &&
                    $arg['sales_total_gross'] === 0;
            }));


        $this->purifier->shouldReceive('purify')->andReturn('Test Description');

        $result = $this->createEventService->createEvent($eventData, $eventSettings);

        $this->assertInstanceOf(EventDomainObject::class, $result);
        $this->assertEquals($eventData->getId(), $result->getId());
    }

    public function testCreateEventWithoutEventSettings(): void
    {
        $eventData = $this->createMockEventDomainObject();
        $organizer = $this->createMockOrganizerDomainObject();

        $this->databaseManager->shouldReceive('beginTransaction')->once();
        $this->databaseManager->shouldReceive('commit')->once();

        $this->organizerRepository->shouldReceive('findFirstWhere')->andReturn($organizer);
        $this->eventRepository->shouldReceive('create')->andReturn($eventData);

        $this->purifier->shouldReceive('purify')->andReturn('Test Description');

        $this->eventSettingsRepository->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($eventData, $organizer) {
                return $arg['event_id'] === $eventData->getId() &&
                    $arg['homepage_background_type'] === HomepageBackgroundType::COLOR->name &&
                    $arg['support_email'] === $organizer->getEmail();
            }));

        $this->eventStatisticsRepository->shouldReceive('create');

        $result = $this->createEventService->createEvent($eventData);

        $this->assertInstanceOf(EventDomainObject::class, $result);
    }

    public function testCreateEventThrowsOrganizerNotFoundException(): void
    {
        $eventData = $this->createMockEventDomainObject();

        $this->databaseManager->shouldReceive('beginTransaction')->once();

        $this->organizerRepository->shouldReceive('findFirstWhere')->andReturnNull();

        $this->expectException(OrganizerNotFoundException::class);

        $this->createEventService->createEvent($eventData);
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

    private function createMockOrganizerDomainObject(): OrganizerDomainObject
    {
        return Mockery::mock(OrganizerDomainObject::class, function ($mock) {
            $mock->shouldReceive('getEmail')->andReturn('organizer@example.com');
        });
    }
}
