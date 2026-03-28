<?php

namespace Tests\Unit\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListAttendeePublicHandler;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;
use Mockery as m;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class GetCheckInListAttendeePublicHandlerTest extends TestCase
{
    private CheckInListDataService $checkInListDataService;
    private AttendeeRepositoryInterface $attendeeRepository;
    private GetCheckInListAttendeePublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListDataService = m::mock(CheckInListDataService::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);

        $this->handler = new GetCheckInListAttendeePublicHandler(
            $this->attendeeRepository,
            $this->checkInListDataService
        );
    }

    public function testHandleThrowsNotFoundIfCheckInListMissing(): void
    {
        $this->checkInListDataService
            ->shouldReceive('getCheckInList')
            ->once()
            ->andThrow(new CannotCheckInException(__('Check-in list not found')));

        $this->expectException(CannotCheckInException::class);

        $this->handler->handle('short-id', 'attendee-public-id');
    }

    public function testHandleThrowsCannotCheckInIfListExpired(): void
    {
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('isPasswordProtected')->andReturn(false);
        $checkInList->shouldReceive('getExpiresAt')->twice()->andReturn(now()->subMinute());

        $this->checkInListDataService
            ->shouldReceive('getCheckInList')
            ->once()
            ->andReturn($checkInList);

        $this->expectException(CannotCheckInException::class);

        $this->handler->handle('short-id', 'attendee-public-id');
    }

    public function testHandleThrowsCannotCheckInIfListNotActiveYet(): void
    {
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('isPasswordProtected')->andReturn(false);
        $checkInList->shouldReceive('getExpiresAt')->once()->andReturn(null);
        $checkInList->shouldReceive('getActivatesAt')->twice()->andReturn(now()->addMinute());

        $this->checkInListDataService
            ->shouldReceive('getCheckInList')
            ->once()
            ->andReturn($checkInList);

        $this->expectException(CannotCheckInException::class);

        $this->handler->handle('short-id', 'attendee-public-id');
    }

    public function testHandleReturnsAttendeeSuccessfully(): void
    {
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('isPasswordProtected')->andReturn(false);
        $checkInList->shouldReceive('getExpiresAt')->once()->andReturn(null);
        $checkInList->shouldReceive('getActivatesAt')->once()->andReturn(null);
        $checkInList->shouldReceive('getEventId')->once()->andReturn(123);

        $attendee = m::mock(AttendeeDomainObject::class);

        $this->checkInListDataService
            ->shouldReceive('getCheckInList')
            ->once()
            ->andReturn($checkInList);

        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'public_id' => 'attendee-public-id',
                'event_id' => 123,
            ])
            ->andReturn($attendee);

        $result = $this->handler->handle('short-id', 'attendee-public-id');

        $this->assertSame($attendee, $result);
    }

    public function testHandleThrowsExceptionIfInvalidPasswordProvided(): void
    {
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('isPasswordProtected')->andReturn(true);
        $checkInList->shouldReceive('getPassword')->andReturn('secret');

        $this->checkInListDataService
            ->shouldReceive('getCheckInList')
            ->once()
            ->andReturn($checkInList);

        $this->expectException(CannotCheckInException::class);
        $this->expectExceptionMessage('Invalid password provided');

        $this->handler->handle('short-id', 'attendee-public-id', 'wrong-password');
    }

    public function testHandleReturnsAttendeeIfValidPasswordProvided(): void
    {
        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('isPasswordProtected')->andReturn(true);
        $checkInList->shouldReceive('getPassword')->andReturn('secret');
        $checkInList->shouldReceive('getExpiresAt')->once()->andReturn(null);
        $checkInList->shouldReceive('getActivatesAt')->once()->andReturn(null);
        $checkInList->shouldReceive('getEventId')->once()->andReturn(123);

        $attendee = m::mock(AttendeeDomainObject::class);

        $this->checkInListDataService
            ->shouldReceive('getCheckInList')
            ->once()
            ->andReturn($checkInList);

        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'public_id' => 'attendee-public-id',
                'event_id' => 123,
            ])
            ->andReturn($attendee);

        $result = $this->handler->handle('short-id', 'attendee-public-id', 'secret');

        $this->assertSame($attendee, $result);
    }
}
