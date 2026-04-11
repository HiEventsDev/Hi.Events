<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Mail\SendEventEmailMessagesService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Mockery;
use Symfony\Component\HttpKernel\Log\Logger;
use Tests\TestCase;

class SendEventEmailMessagesServiceCheckinTest extends TestCase
{
    private SendEventEmailMessagesService $service;
    private AttendeeRepositoryInterface $attendeeRepository;
    private EventRepositoryInterface $eventRepository;
    private MessageRepositoryInterface $messageRepository;
    private OrderRepositoryInterface $orderRepository;
    private UserRepositoryInterface $userRepository;
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);

        $this->service = new SendEventEmailMessagesService(
            orderRepository: $this->orderRepository,
            attendeeRepository: $this->attendeeRepository,
            eventRepository: $this->eventRepository,
            messageRepository: $this->messageRepository,
            userRepository: $this->userRepository,
            logger: new Logger(),
            dispatcher: $this->dispatcher,
        );
    }

    private function createMessageDTO(MessageTypeEnum $type): SendMessageDTO
    {
        return SendMessageDTO::fromArray([
            'account_id' => 1,
            'event_id' => 10,
            'subject' => 'Test',
            'message' => 'Test message',
            'type' => $type,
            'is_test' => false,
            'send_copy_to_current_user' => false,
            'sent_by_user_id' => 1,
            'id' => 100,
        ]);
    }

    private function mockEvent(): EventDomainObject
    {
        $eventSettings = Mockery::mock(EventSettingDomainObject::class);
        $organizer = Mockery::mock(OrganizerDomainObject::class);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getEventSettings')->andReturn($eventSettings);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);

        return $event;
    }

    private function setupEventRepository(EventDomainObject $event): void
    {
        $this->eventRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->eventRepository
            ->shouldReceive('findById')
            ->andReturn($event);

        $this->orderRepository
            ->shouldReceive('findFirstWhere')
            ->andReturnNull();
    }

    private function createAttendee(string $email, string $firstName, string $lastName): AttendeeDomainObject
    {
        $attendee = new AttendeeDomainObject();
        $attendee->setEmail($email);
        $attendee->setFirstName($firstName);
        $attendee->setLastName($lastName);

        return $attendee;
    }

    public function testSendCheckedInMessagesQueriesCheckedInAttendees(): void
    {
        $event = $this->mockEvent();
        $this->setupEventRepository($event);
        $messageDTO = $this->createMessageDTO(MessageTypeEnum::CHECKED_IN_ATTENDEES);

        $attendee = $this->createAttendee('checked@example.com', 'John', 'Doe');

        $this->attendeeRepository
            ->shouldReceive('findCheckedInAttendees')
            ->once()
            ->with(10, ['first_name', 'last_name', 'email'])
            ->andReturn(new Collection([$attendee]));

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once();

        $this->messageRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->service->send($messageDTO);
    }

    public function testSendNotCheckedInMessagesQueriesNotCheckedInAttendees(): void
    {
        $event = $this->mockEvent();
        $this->setupEventRepository($event);
        $messageDTO = $this->createMessageDTO(MessageTypeEnum::NOT_CHECKED_IN_ATTENDEES);

        $attendee = $this->createAttendee('noshow@example.com', 'Jane', 'Doe');

        $this->attendeeRepository
            ->shouldReceive('findNotCheckedInAttendees')
            ->once()
            ->with(10, ['first_name', 'last_name', 'email'])
            ->andReturn(new Collection([$attendee]));

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once();

        $this->messageRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->service->send($messageDTO);
    }

    public function testSendCheckedInMessagesWithNoAttendeesDoesNotDispatch(): void
    {
        $event = $this->mockEvent();
        $this->setupEventRepository($event);
        $messageDTO = $this->createMessageDTO(MessageTypeEnum::CHECKED_IN_ATTENDEES);

        $this->attendeeRepository
            ->shouldReceive('findCheckedInAttendees')
            ->once()
            ->andReturn(new Collection());

        $this->dispatcher
            ->shouldNotReceive('dispatch');

        $this->messageRepository
            ->shouldReceive('updateWhere')
            ->once();

        $this->service->send($messageDTO);
    }

    protected function tearDown(): void
    {
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        Mockery::close();
        parent::tearDown();
    }
}
