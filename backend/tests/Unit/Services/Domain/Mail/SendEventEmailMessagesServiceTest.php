<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Jobs\Event\SendEventEmailJob;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Domain\Mail\SendEventEmailMessagesService;
use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Log\Logger;
use Tests\TestCase;

class SendEventEmailMessagesServiceTest extends TestCase
{
    private const SENDER_EMAIL = 'organizer@example.com';

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private MessageRepositoryInterface|MockInterface $messageRepository;

    private array $recipients = [];

    private array $statusUpdates = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_occurrence_filter_is_empty_for_a_message_queued_by_a_previous_release(): void
    {
        $messageData = (new ReflectionClass(SendMessageDTO::class))->newInstanceWithoutConstructor();

        $service = new SendEventEmailMessagesService(
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(AttendeeRepositoryInterface::class),
            Mockery::mock(EventRepositoryInterface::class),
            Mockery::mock(MessageRepositoryInterface::class),
            Mockery::mock(UserRepositoryInterface::class),
            Mockery::mock(Logger::class),
            Mockery::mock(Dispatcher::class),
        );

        $occurrenceWhere = new ReflectionMethod($service, 'occurrenceWhere');

        $this->assertSame([], $occurrenceWhere->invoke($service, $messageData));
    }

    public static function messageTypes(): array
    {
        return [
            'individual attendees' => [MessageTypeEnum::INDIVIDUAL_ATTENDEES],
            'order owner' => [MessageTypeEnum::ORDER_OWNER],
            'ticket holders' => [MessageTypeEnum::TICKET_HOLDERS],
            'all attendees' => [MessageTypeEnum::ALL_ATTENDEES],
            'order owners with a product' => [MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT],
        ];
    }

    #[DataProvider('messageTypes')]
    public function test_test_message_only_emails_the_sender(MessageTypeEnum $type): void
    {
        $service = $this->makeService(
            orders: [$this->makeOrder('buyer@example.com'), $this->makeOrder('buyer2@example.com')],
            attendees: [$this->makeAttendee('attendee@example.com')],
        );

        $service->send($this->makeMessageData($type, isTest: true));

        $this->assertSame([self::SENDER_EMAIL], $this->recipients);
        $this->assertSame([MessageStatus::SENT->name], $this->statusUpdates);
    }

    public function test_test_message_to_order_owners_with_a_product_emails_the_sender_when_no_orders_match(): void
    {
        $service = $this->makeService(orders: [], attendees: []);

        $service->send($this->makeMessageData(MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT, isTest: true));

        $this->assertSame([self::SENDER_EMAIL], $this->recipients);
        $this->assertSame([MessageStatus::SENT->name], $this->statusUpdates);
    }

    public function test_live_message_to_order_owners_with_a_product_emails_every_order_owner(): void
    {
        $service = $this->makeService(
            orders: [$this->makeOrder('buyer@example.com'), $this->makeOrder('buyer2@example.com')],
            attendees: [],
        );

        $service->send($this->makeMessageData(MessageTypeEnum::ORDER_OWNERS_WITH_PRODUCT, isTest: false));

        $this->assertSame(['buyer@example.com', 'buyer2@example.com'], $this->recipients);
    }

    public function test_live_message_to_an_order_owner_emails_the_buyer_and_a_requested_copy_to_the_sender(): void
    {
        $service = $this->makeService(orders: [$this->makeOrder('buyer@example.com')], attendees: []);

        $service->send($this->makeMessageData(MessageTypeEnum::ORDER_OWNER, isTest: false, sendCopy: true));

        $this->assertSame([self::SENDER_EMAIL, 'buyer@example.com'], $this->recipients);
    }

    public function test_live_message_to_all_attendees_emails_each_attendee(): void
    {
        $service = $this->makeService(
            orders: [],
            attendees: [$this->makeAttendee('one@example.com'), $this->makeAttendee('two@example.com')],
        );

        $service->send($this->makeMessageData(MessageTypeEnum::ALL_ATTENDEES, isTest: false));

        $this->assertSame(['one@example.com', 'two@example.com'], $this->recipients);
    }

    private function makeService(array $orders, array $attendees): SendEventEmailMessagesService
    {
        $event = (new EventDomainObject)
            ->setId(10)
            ->setEventSettings(new EventSettingDomainObject);

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $eventRepository->shouldReceive('findById')->with(10)->andReturn($event);

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('findFirstWhere')
            ->andReturnUsing(fn (array $where) => $where['id'] !== null ? ($orders[0] ?? null) : null);
        $this->orderRepository->shouldReceive('findOrdersAssociatedWithProducts')->andReturn(collect($orders));

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect($attendees));
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(collect($attendees));

        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->messageRepository->shouldReceive('updateWhere')
            ->andReturnUsing(function (array $attributes) {
                $this->statusUpdates[] = $attributes['status'];

                return 1;
            });

        $sender = (new UserDomainObject)
            ->setId(99)
            ->setEmail(self::SENDER_EMAIL)
            ->setFirstName('Org')
            ->setLastName('Anizer');

        $userRepository = Mockery::mock(UserRepositoryInterface::class);
        $userRepository->shouldReceive('findById')->with(99)->andReturn($sender);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->andReturnUsing(function (SendEventEmailJob $job) {
                $this->recipients[] = (fn () => $this->email)->call($job);
            });

        return new SendEventEmailMessagesService(
            $this->orderRepository,
            $this->attendeeRepository,
            $eventRepository,
            $this->messageRepository,
            $userRepository,
            Mockery::mock(Logger::class),
            $dispatcher,
        );
    }

    private function makeMessageData(MessageTypeEnum $type, bool $isTest, bool $sendCopy = false): SendMessageDTO
    {
        return new SendMessageDTO(
            account_id: 1,
            event_id: 10,
            subject: 'Subject',
            message: '<p>Body</p>',
            type: $type,
            is_test: $isTest,
            send_copy_to_current_user: $sendCopy,
            sent_by_user_id: 99,
            order_id: $type === MessageTypeEnum::ORDER_OWNER ? 5 : null,
            order_statuses: ['COMPLETED'],
            id: 1,
            attendee_ids: [20],
            product_ids: [30],
        );
    }

    private function makeOrder(string $email): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(5)
            ->setEmail($email)
            ->setFirstName('Buyer')
            ->setLastName('Person');
    }

    private function makeAttendee(string $email): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setEmail($email)
            ->setFirstName('Attendee')
            ->setLastName('Person');
    }
}
