<?php

namespace Tests\Unit\Services\Application\Handlers\Message;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Application\Handlers\Message\SendMessageHandler;
use HiEvents\Services\Domain\Message\MessagingEligibilityService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Bus;
use Mockery as m;
use Tests\TestCase;

class SendMessageHandlerScheduledTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;
    private AttendeeRepositoryInterface $attendeeRepository;
    private ProductRepositoryInterface $productRepository;
    private MessageRepositoryInterface $messageRepository;
    private AccountRepositoryInterface $accountRepository;
    private EventRepositoryInterface $eventRepository;
    private HtmlPurifierService $purifier;
    private Repository $config;
    private MessagingEligibilityService $eligibilityService;
    private SendMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);
        $this->productRepository = m::mock(ProductRepositoryInterface::class);
        $this->messageRepository = m::mock(MessageRepositoryInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->purifier = m::mock(HtmlPurifierService::class);
        $this->config = m::mock(Repository::class);
        $this->eligibilityService = m::mock(MessagingEligibilityService::class);

        $this->handler = new SendMessageHandler(
            $this->orderRepository,
            $this->attendeeRepository,
            $this->productRepository,
            $this->messageRepository,
            $this->accountRepository,
            $this->eventRepository,
            $this->purifier,
            $this->config,
            $this->eligibilityService
        );
    }

    private function setupAccountMocks(): void
    {
        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(Carbon::now());
        $account->shouldReceive('getIsManuallyVerified')->andReturn(true);

        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('America/New_York');

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);
        $this->eventRepository->shouldReceive('findById')->with(101)->andReturn($event);
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->eligibilityService->shouldReceive('checkTierLimits')->andReturn(null);
        $this->eligibilityService->shouldReceive('checkEligibility')->andReturn(null);

        $this->purifier->shouldReceive('purify')->andReturn('<p>Test</p>');
    }

    private function setupRepositoryMocks(): void
    {
        $attendee = new AttendeeDomainObject();
        $attendee->setId(10);

        $product = new ProductDomainObject();
        $product->setId(20);

        $order = new OrderDomainObject();
        $order->setId(5);

        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect([$attendee]));
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(collect([$product]));
        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);
    }

    public function testFutureScheduledAtSetsScheduledStatusAndDoesNotDispatchJob(): void
    {
        Bus::fake();

        $this->setupAccountMocks();
        $this->setupRepositoryMocks();

        $message = m::mock(MessageDomainObject::class);
        $message->shouldReceive('getId')->andReturn(1);
        $message->shouldReceive('getOrderId')->andReturn(5);
        $message->shouldReceive('getAttendeeIds')->andReturn([10]);
        $message->shouldReceive('getProductIds')->andReturn([20]);
        $message->shouldReceive('getStatus')->andReturn(MessageStatus::SCHEDULED->name);

        $this->messageRepository->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                return $data['status'] === MessageStatus::SCHEDULED->name
                    && $data['scheduled_at'] !== null
                    && $data['sent_at'] === null;
            })
            ->andReturn($message);

        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 101,
            subject: 'Hello',
            message: '<p>Test</p>',
            type: MessageTypeEnum::INDIVIDUAL_ATTENDEES,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 99,
            order_id: 5,
            order_statuses: [],
            attendee_ids: [10],
            product_ids: [20],
            scheduled_at: Carbon::now('America/New_York')->addHour()->format('Y-m-d\TH:i'),
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($message, $result);
        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    public function testNoScheduledAtDispatchesJobImmediately(): void
    {
        Bus::fake();

        $this->setupAccountMocks();
        $this->setupRepositoryMocks();

        $message = m::mock(MessageDomainObject::class);
        $message->shouldReceive('getId')->andReturn(1);
        $message->shouldReceive('getOrderId')->andReturn(5);
        $message->shouldReceive('getAttendeeIds')->andReturn([10]);
        $message->shouldReceive('getProductIds')->andReturn([20]);
        $message->shouldReceive('getStatus')->andReturn(MessageStatus::PROCESSING->name);

        $this->messageRepository->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                return $data['status'] === MessageStatus::PROCESSING->name
                    && $data['sent_at'] !== null;
            })
            ->andReturn($message);

        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 101,
            subject: 'Hello',
            message: '<p>Test</p>',
            type: MessageTypeEnum::INDIVIDUAL_ATTENDEES,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 99,
            order_id: 5,
            order_statuses: [],
            attendee_ids: [10],
            product_ids: [20],
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($message, $result);
        Bus::assertDispatched(SendMessagesJob::class);
    }

    public function testIsTestWithScheduledAtSendsImmediately(): void
    {
        Bus::fake();

        $this->setupAccountMocks();
        $this->setupRepositoryMocks();

        $message = m::mock(MessageDomainObject::class);
        $message->shouldReceive('getId')->andReturn(1);
        $message->shouldReceive('getOrderId')->andReturn(5);
        $message->shouldReceive('getAttendeeIds')->andReturn([10]);
        $message->shouldReceive('getProductIds')->andReturn([20]);
        $message->shouldReceive('getStatus')->andReturn(MessageStatus::PROCESSING->name);

        $this->messageRepository->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                return $data['status'] === MessageStatus::PROCESSING->name
                    && $data['sent_at'] !== null;
            })
            ->andReturn($message);

        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 101,
            subject: 'Hello',
            message: '<p>Test</p>',
            type: MessageTypeEnum::INDIVIDUAL_ATTENDEES,
            is_test: true,
            send_copy_to_current_user: false,
            sent_by_user_id: 99,
            order_id: 5,
            order_statuses: [],
            attendee_ids: [10],
            product_ids: [20],
            scheduled_at: Carbon::now('America/New_York')->addHour()->format('Y-m-d\TH:i'),
        );

        $result = $this->handler->handle($dto);

        $this->assertSame($message, $result);
        Bus::assertDispatched(SendMessagesJob::class);
    }
}
