<?php

namespace Tests\Unit\Services\Domain\CheckInList;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;
use Mockery;
use Tests\TestCase;

class CheckInListDataServiceTest extends TestCase
{
    public function test_attendees_are_looked_up_only_within_the_check_in_lists_event(): void
    {
        $attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $attendeeRepository->shouldReceive('findWhereIn')
            ->once()
            ->with('public_id', ['A-1234567'], ['event_id' => 7])
            ->andReturn(collect([(new AttendeeDomainObject)->setPublicId('A-1234567')]));

        $service = new CheckInListDataService(Mockery::mock(CheckInListRepositoryInterface::class), $attendeeRepository);

        $attendees = $service->getAttendees((new CheckInListDomainObject)->setEventId(7), collect(['A-1234567']));

        $this->assertCount(1, $attendees);
    }

    public function test_a_code_from_another_event_is_reported_as_invalid_without_naming_anyone(): void
    {
        $attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect());

        $service = new CheckInListDataService(Mockery::mock(CheckInListRepositoryInterface::class), $attendeeRepository);

        $this->expectException(CannotCheckInException::class);
        $this->expectExceptionMessage('Invalid attendee code detected: A-7654321');

        $service->getAttendees((new CheckInListDomainObject)->setEventId(7), collect(['A-7654321']));
    }
}
