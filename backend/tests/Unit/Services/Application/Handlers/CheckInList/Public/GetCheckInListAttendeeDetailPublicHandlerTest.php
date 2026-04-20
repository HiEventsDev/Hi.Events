<?php

namespace Tests\Unit\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\GetCheckInListAttendeeDetailPublicHandler;
use Mockery as m;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class GetCheckInListAttendeeDetailPublicHandlerTest extends TestCase
{
    private CheckInListRepositoryInterface $checkInListRepository;
    private AttendeeRepositoryInterface $attendeeRepository;
    private GetCheckInListAttendeeDetailPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListRepository = m::mock(CheckInListRepositoryInterface::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);

        $this->handler = new GetCheckInListAttendeeDetailPublicHandler(
            $this->attendeeRepository,
            $this->checkInListRepository
        );
    }

    public function testHandleThrowsNotFoundIfCheckInListMissing(): void
    {
        $this->checkInListRepository
            ->shouldReceive('loadRelation')->andReturnSelf();
        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle('short-id', 'A-123', null);
    }

    public function testHandleThrowsNotFoundIfAttendeeMissing(): void
    {
        $checkInList = $this->buildList(eventId: 5);

        $this->checkInListRepository
            ->shouldReceive('loadRelation')->andReturnSelf();
        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn($checkInList);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')->andReturnSelf();
        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['public_id' => 'A-123', 'event_id' => 5])
            ->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle('short-id', 'A-123', null);
    }

    public function testAnonymousRequestRespectsListVisibilityFlags(): void
    {
        $checkInList = $this->buildList(
            eventId: 5,
            accountId: 77,
            showNotes: false,
            showQuestions: true,
            showOrderDetails: false,
        );
        $attendee = m::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getCheckIns')->andReturn(null);

        $this->setupRepos($checkInList, $attendee);

        $result = $this->handler->handle('short-id', 'A-123', null);

        $this->assertFalse($result->showNotes);
        $this->assertTrue($result->showQuestionAnswers);
        $this->assertFalse($result->showOrderDetails);
    }

    public function testAuthenticatedStaffBypassesVisibilityFlags(): void
    {
        $checkInList = $this->buildList(
            eventId: 5,
            accountId: 77,
            showNotes: false,
            showQuestions: false,
            showOrderDetails: false,
        );
        $attendee = m::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getCheckIns')->andReturn(null);

        $this->setupRepos($checkInList, $attendee);

        $result = $this->handler->handle('short-id', 'A-123', staffAccountId: 77);

        $this->assertTrue($result->showNotes);
        $this->assertTrue($result->showQuestionAnswers);
        $this->assertTrue($result->showOrderDetails);
    }

    public function testAuthenticatedUserFromDifferentAccountStillFiltered(): void
    {
        $checkInList = $this->buildList(
            eventId: 5,
            accountId: 77,
            showNotes: false,
            showQuestions: false,
            showOrderDetails: false,
        );
        $attendee = m::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getCheckIns')->andReturn(null);

        $this->setupRepos($checkInList, $attendee);

        $result = $this->handler->handle('short-id', 'A-123', staffAccountId: 88);

        $this->assertFalse($result->showNotes);
        $this->assertFalse($result->showQuestionAnswers);
        $this->assertFalse($result->showOrderDetails);
    }

    private function buildList(
        int  $eventId = 5,
        int  $accountId = 1,
        bool $showNotes = true,
        bool $showQuestions = true,
        bool $showOrderDetails = true,
    ): CheckInListDomainObject {
        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getAccountId')->andReturn($accountId);

        $checkInList = m::mock(CheckInListDomainObject::class);
        $checkInList->shouldReceive('getId')->andReturn(1);
        $checkInList->shouldReceive('getEventId')->andReturn($eventId);
        $checkInList->shouldReceive('getEvent')->andReturn($event);
        $checkInList->shouldReceive('getPublicShowAttendeeNotes')->andReturn($showNotes);
        $checkInList->shouldReceive('getPublicShowQuestionAnswers')->andReturn($showQuestions);
        $checkInList->shouldReceive('getPublicShowOrderDetails')->andReturn($showOrderDetails);
        return $checkInList;
    }

    private function setupRepos(CheckInListDomainObject $checkInList, $attendee): void
    {
        $this->checkInListRepository
            ->shouldReceive('loadRelation')->andReturnSelf();
        $this->checkInListRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($checkInList);

        $this->attendeeRepository
            ->shouldReceive('loadRelation')->andReturnSelf();
        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->andReturn($attendee);
    }
}
