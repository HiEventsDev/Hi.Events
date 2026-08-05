<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RecurrenceRuleExclusionServiceTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private RecurrenceRuleExclusionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->service = new RecurrenceRuleExclusionService($this->eventRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_add_exclusions_appends_datetime_in_event_timezone(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'frequency' => 'weekly',
            'excluded_occurrences' => [],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->with(1)->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, [
                EventDomainObjectAbstract::RECURRENCE_RULE => [
                    'frequency' => 'weekly',
                    'excluded_occurrences' => ['2026-06-15 10:00'],
                ],
            ]);

        $this->service->addExclusions(1, ['2026-06-15 10:00:00']);

        $this->assertTrue(true);
    }

    public function test_add_exclusions_does_nothing_when_event_is_not_recurring(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->service->addExclusions(1, ['2026-06-15 10:00:00']);

        $this->assertTrue(true);
    }

    public function test_add_exclusions_skips_duplicates_and_does_not_write_when_no_changes(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_occurrences' => ['2026-06-15 10:00'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->service->addExclusions(1, ['2026-06-15 10:00:00']);

        $this->assertTrue(true);
    }

    public function test_add_exclusions_appends_to_existing_list(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_occurrences' => ['2026-06-15 10:00'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(fn ($attrs) => $attrs[EventDomainObjectAbstract::RECURRENCE_RULE]['excluded_occurrences']
                === ['2026-06-15 10:00', '2026-07-20 14:00']));

        $this->service->addExclusions(1, ['2026-07-20 14:00:00']);

        $this->assertTrue(true);
    }

    public function test_add_exclusions_handles_recurrence_rule_as_json_string(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn(
            json_encode(['frequency' => 'daily', 'excluded_occurrences' => []])
        );

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(fn ($attrs) => $attrs[EventDomainObjectAbstract::RECURRENCE_RULE]['excluded_occurrences']
                === ['2026-08-01 09:00']));

        $this->service->addExclusions(1, ['2026-08-01 09:00:00']);

        $this->assertTrue(true);
    }

    public function test_add_exclusions_deduplicates_input(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_occurrences' => [],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(fn ($attrs) => $attrs[EventDomainObjectAbstract::RECURRENCE_RULE]['excluded_occurrences']
                === ['2026-06-15 10:00']));

        $this->service->addExclusions(1, ['2026-06-15 10:00:00', '2026-06-15 10:00:00']);

        $this->assertTrue(true);
    }

    public function test_remove_exclusion_removes_from_excluded_dates(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_dates' => ['2026-05-15', '2026-06-01', '2026-07-04'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(static function (array $attrs): bool {
                $rule = $attrs[EventDomainObjectAbstract::RECURRENCE_RULE] ?? null;

                return is_array($rule)
                    && $rule['excluded_dates'] === ['2026-05-15', '2026-07-04'];
            }));

        $this->service->removeExclusion(1, '2026-06-01 10:00:00');

        $this->assertTrue(true);
    }

    public function test_remove_exclusion_removes_from_excluded_occurrences(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_occurrences' => ['2026-06-01 10:00', '2026-07-20 14:00'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(1, Mockery::on(static function (array $attrs): bool {
                $rule = $attrs[EventDomainObjectAbstract::RECURRENCE_RULE] ?? null;

                return is_array($rule)
                    && $rule['excluded_occurrences'] === ['2026-07-20 14:00'];
            }));

        $this->service->removeExclusion(1, '2026-06-01 10:00:00');

        $this->assertTrue(true);
    }

    public function test_remove_exclusion_does_nothing_when_date_is_not_excluded(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::RECURRING->name);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $event->shouldReceive('getRecurrenceRule')->andReturn([
            'excluded_dates' => ['2026-05-15'],
            'excluded_occurrences' => ['2026-07-20 14:00'],
        ]);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->service->removeExclusion(1, '2026-06-01 10:00:00');

        $this->assertTrue(true);
    }

    public function test_remove_exclusion_does_nothing_when_event_is_not_recurring(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getType')->andReturn(EventType::SINGLE->name);

        $this->eventRepository->shouldReceive('findByIdLocked')->once()->andReturn($event);
        $this->eventRepository->shouldNotReceive('updateFromArray');

        $this->service->removeExclusion(1, '2026-06-01 10:00:00');

        $this->assertTrue(true);
    }
}
