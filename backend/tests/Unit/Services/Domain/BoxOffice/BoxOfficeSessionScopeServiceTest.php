<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionScopeService;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxOfficeSessionScopeServiceTest extends TestCase
{
    private MockInterface|CheckInListRepositoryInterface $checkInListRepository;

    private MockInterface|CheckInListActivityValidator $checkInListActivityValidator;

    private BoxOfficeSessionScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListRepository = Mockery::mock(CheckInListRepositoryInterface::class);
        $this->checkInListActivityValidator = Mockery::mock(CheckInListActivityValidator::class);
        $this->service = new BoxOfficeSessionScopeService(
            boxOfficeRepository: Mockery::mock(BoxOfficeRepositoryInterface::class),
            checkInListRepository: $this->checkInListRepository,
            checkInListActivityValidator: $this->checkInListActivityValidator,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function occurrence(int $id, string $status = 'ACTIVE'): EventOccurrenceDomainObject
    {
        return (new EventOccurrenceDomainObject)->setId($id)->setStatus($status)->setStartDate('2030-01-0'.$id.' 19:00:00');
    }

    private function recurringBoxOffice(?int $scopedOccurrenceId = null): BoxOfficeDomainObject
    {
        $event = (new EventDomainObject)
            ->setId(7)
            ->setType(EventType::RECURRING->name)
            ->setEventOccurrences(collect([$this->occurrence(1), $this->occurrence(2), $this->occurrence(3, EventOccurrenceStatus::CANCELLED->name)]));

        $boxOffice = (new BoxOfficeDomainObject)->setId(12)->setEventId(7)->setEventOccurrenceId($scopedOccurrenceId);
        $boxOffice->setEvent($event);
        $boxOffice->setEventOccurrence($scopedOccurrenceId === null ? null : $this->occurrence($scopedOccurrenceId));

        return $boxOffice;
    }

    public function test_only_unscoped_recurring_box_offices_can_switch_occurrence(): void
    {
        $this->assertTrue($this->service->canSwitchOccurrence($this->recurringBoxOffice()));
        $this->assertFalse($this->service->canSwitchOccurrence($this->recurringBoxOffice(2)));

        $single = $this->recurringBoxOffice();
        $single->getEvent()->setType(EventType::SINGLE->name);
        $this->assertFalse($this->service->canSwitchOccurrence($single));
    }

    public function test_resolves_the_requested_occurrence_and_default_check_in_list(): void
    {
        $checkInList = (new CheckInListDomainObject)->setId(5)->setShortId('cil_x');
        $this->checkInListRepository->shouldReceive('findFirstWhere')->once()->with([
            'event_id' => 7,
            'is_system_default' => true,
        ])->andReturn($checkInList);
        $this->checkInListActivityValidator->shouldReceive('assertActive')->once()->with($checkInList);

        $scope = $this->service->resolve($this->recurringBoxOffice(), 2);

        $this->assertSame(2, $scope->event_occurrence->getId());
        $this->assertSame('cil_x', $scope->check_in_list_short_id);
        $this->assertTrue($scope->check_in_available);
        $this->assertNull($scope->check_in_unavailable_reason);
    }

    public function test_rejects_cancelled_or_unknown_occurrences(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->resolve($this->recurringBoxOffice(), 3);
    }

    public function test_scoped_box_office_ignores_the_requested_occurrence(): void
    {
        $this->checkInListRepository->shouldReceive('findFirstWhere')->andReturnNull();

        $scope = $this->service->resolve($this->recurringBoxOffice(2), 1);

        $this->assertSame(2, $scope->event_occurrence->getId());
        $this->assertFalse($scope->check_in_available);
        $this->assertNotNull($scope->check_in_unavailable_reason);
    }

    public function test_reports_an_inactive_or_mismatched_check_in_list(): void
    {
        $inactive = (new CheckInListDomainObject)->setId(5)->setShortId('cil_x');
        $this->checkInListRepository->shouldReceive('findFirstWhere')->andReturn($inactive);
        $this->checkInListActivityValidator
            ->shouldReceive('assertActive')
            ->andThrow(new CannotCheckInException('Check-in list has expired'));

        $scope = $this->service->resolve($this->recurringBoxOffice(), 1);

        $this->assertFalse($scope->check_in_available);
        $this->assertSame('Check-in list has expired', $scope->check_in_unavailable_reason);
    }
}
