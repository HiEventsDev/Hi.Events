<?php

namespace Tests\Unit\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\ResendAttendeeTicketDTO;
use HiEvents\Services\Application\Handlers\Attendee\ResendAttendeeTicketHandler;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ResendAttendeeTicketHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ATTENDEE_ID = 20;

    private SendAttendeeTicketService|MockInterface $sendAttendeeTicketService;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private ResendAttendeeTicketHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sendAttendeeTicketService = Mockery::mock(SendAttendeeTicketService::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);

        $this->handler = new ResendAttendeeTicketHandler(
            $this->sendAttendeeTicketService,
            $this->attendeeRepository,
            Mockery::mock(EventRepositoryInterface::class),
            Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
        );
    }

    public function test_a_box_office_attendee_without_an_email_cannot_have_a_ticket_resent(): void
    {
        $this->givenAttendeeIsFound(email: null);

        $this->sendAttendeeTicketService->shouldNotReceive('send');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(new ResendAttendeeTicketDTO(
            attendeeId: self::ATTENDEE_ID,
            eventId: self::EVENT_ID,
        ));
    }

    private function givenAttendeeIsFound(?string $email): void
    {
        $attendee = (new AttendeeDomainObject)
            ->setId(self::ATTENDEE_ID)
            ->setEventId(self::EVENT_ID)
            ->setEmail($email)
            ->setStatus(AttendeeStatus::ACTIVE->name);

        $this->attendeeRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->attendeeRepository->shouldReceive('findFirstWhere')->once()->andReturn($attendee);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
