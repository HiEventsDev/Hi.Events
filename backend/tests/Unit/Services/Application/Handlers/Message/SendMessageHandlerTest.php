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
use HiEvents\Exceptions\AccountNotVerifiedException;
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
use Illuminate\Validation\ValidationException;
use Mockery as m;
use Tests\TestCase;

class SendMessageHandlerTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;

    private AttendeeRepositoryInterface $attendeeRepository;

    private ProductRepositoryInterface $productRepository;

    private MessageRepositoryInterface $messageRepository;

    private AccountRepositoryInterface $accountRepository;

    private HtmlPurifierService $purifier;

    private Repository $config;

    private MessagingEligibilityService $eligibilityService;

    private EventRepositoryInterface $eventRepository;

    private SendMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);
        $this->productRepository = m::mock(ProductRepositoryInterface::class);
        $this->messageRepository = m::mock(MessageRepositoryInterface::class);
        $this->accountRepository = m::mock(AccountRepositoryInterface::class);
        $this->purifier = m::mock(HtmlPurifierService::class);
        $this->config = m::mock(Repository::class);
        $this->eligibilityService = m::mock(MessagingEligibilityService::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);

        $this->handler = new SendMessageHandler(
            orderRepository: $this->orderRepository,
            attendeeRepository: $this->attendeeRepository,
            productRepository: $this->productRepository,
            messageRepository: $this->messageRepository,
            accountRepository: $this->accountRepository,
            eventRepository: $this->eventRepository,
            purifier: $this->purifier,
            config: $this->config,
            eligibilityService: $this->eligibilityService
        );
    }

    public function test_throws_if_account_not_verified(): void
    {
        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 1,
            subject: 'Subject',
            message: 'Message',
            type: MessageTypeEnum::INDIVIDUAL_ATTENDEES,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 1,
            order_id: null,
            order_statuses: [],
            attendee_ids: [],
            product_ids: []
        );

        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(null);

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);

        $this->expectException(AccountNotVerifiedException::class);

        $this->handler->handle($dto);
    }

    public function test_throws_if_saas_mode_enabled_and_not_manually_verified(): void
    {
        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 1,
            subject: 'Subject',
            message: 'Message',
            type: MessageTypeEnum::INDIVIDUAL_ATTENDEES,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 1,
            order_id: null,
            order_statuses: [],
            attendee_ids: [],
            product_ids: []
        );

        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(Carbon::now());
        $account->shouldReceive('getIsManuallyVerified')->andReturn(false);

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(true);
        $this->config->shouldReceive('get')->with('app.platform_support_email')->andReturn('support@example.com');

        $this->expectException(AccountNotVerifiedException::class);

        $this->handler->handle($dto);
    }

    public function test_handle_creates_message_and_dispatches_job(): void
    {
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

        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $this->eventRepository->shouldReceive('findById')->with(101)->andReturn($event);

        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(Carbon::now());
        $account->shouldReceive('getIsManuallyVerified')->andReturn(true);

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->eligibilityService->shouldReceive('checkTierLimits')->andReturn(null);
        $this->eligibilityService->shouldReceive('checkEligibility')->andReturn(null);

        $this->purifier->shouldReceive('purify')->with('<p>Test</p>')->andReturn('<p>Test</p>');

        $attendee = new AttendeeDomainObject;
        $attendee->setId(10);

        $product = new ProductDomainObject;
        $product->setId(20);

        $order = new OrderDomainObject;
        $order->setId(5);

        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect([$attendee]));
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(collect([$product]));
        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);

        $message = m::mock(MessageDomainObject::class);
        $message->shouldReceive('getId')->andReturn(1);
        $message->shouldReceive('getOrderId')->andReturn(5);
        $message->shouldReceive('getAttendeeIds')->andReturn([10]);
        $message->shouldReceive('getProductIds')->andReturn([20]);
        $message->shouldReceive('getStatus')->andReturn('PROCESSING');

        $this->messageRepository->shouldReceive('create')->andReturn($message);

        Bus::fake();

        $result = $this->handler->handle($dto);

        $this->assertSame($message, $result);

        Bus::assertDispatched(SendMessagesJob::class);
    }

    public function test_handle_estimates_recipients_for_multi_occurrence_targeting(): void
    {
        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 101,
            subject: 'Hi',
            message: '<p>Body</p>',
            type: MessageTypeEnum::ALL_ATTENDEES,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 99,
            event_occurrence_ids: [201, 202, 203],
        );

        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');
        $this->eventRepository->shouldReceive('findById')->with(101)->andReturn($event);

        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(Carbon::now());
        $account->shouldReceive('getIsManuallyVerified')->andReturn(true);
        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);

        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);
        $this->eligibilityService->shouldReceive('checkTierLimits')->andReturn(null);
        $this->eligibilityService->shouldReceive('checkEligibility')->andReturn(null);
        $this->purifier->shouldReceive('purify')->andReturn('<p>Body</p>');

        $this->attendeeRepository
            ->shouldReceive('countWhere')
            ->once()
            ->with(m::on(function (array $where) {
                $conditions = array_values(array_filter($where, 'is_array'));

                return in_array(['event_occurrence_id', 'in', [201, 202, 203]], $conditions, true)
                    && in_array(['email', 'not null', null], $conditions, true);
            }))
            ->andReturn(42);

        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn(null);

        $message = m::mock(MessageDomainObject::class);
        $message->shouldReceive('getId')->andReturn(1);
        $message->shouldReceive('getOrderId')->andReturn(null);
        $message->shouldReceive('getAttendeeIds')->andReturn([]);
        $message->shouldReceive('getProductIds')->andReturn([]);
        $this->messageRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $attrs) {
                return $attrs['event_occurrence_id'] === null
                    && ($attrs['send_data']['event_occurrence_ids'] ?? null) === [201, 202, 203];
            }))
            ->andReturn($message);

        Bus::fake();
        $this->handler->handle($dto);

        Bus::assertDispatched(SendMessagesJob::class, function (SendMessagesJob $job) {
            return $job->messageData->event_occurrence_ids === [201, 202, 203]
                && $job->messageData->event_occurrence_id === null;
        });
    }

    public function test_throws_when_no_selected_attendee_has_an_email(): void
    {
        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 101,
            subject: 'Hello',
            message: '<p>Test</p>',
            type: MessageTypeEnum::INDIVIDUAL_ATTENDEES,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 99,
            order_id: null,
            order_statuses: [],
            attendee_ids: [10],
            product_ids: [],
        );

        $this->givenAVerifiedAccount();

        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect());

        $this->messageRepository->shouldNotReceive('create');

        $this->expectException(ValidationException::class);

        $this->handler->handle($dto);
    }

    public function test_throws_when_the_order_owner_has_no_email(): void
    {
        $dto = new SendMessageDTO(
            account_id: 1,
            event_id: 101,
            subject: 'Hello',
            message: '<p>Test</p>',
            type: MessageTypeEnum::ORDER_OWNER,
            is_test: false,
            send_copy_to_current_user: false,
            sent_by_user_id: 99,
            order_id: 5,
            order_statuses: [],
            attendee_ids: [],
            product_ids: [],
        );

        $this->givenAVerifiedAccount();

        $order = new OrderDomainObject;
        $order->setId(5);
        $order->setEmail(null);

        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);

        $this->messageRepository->shouldNotReceive('create');

        $this->expectException(ValidationException::class);

        $this->handler->handle($dto);
    }

    private function givenAVerifiedAccount(): void
    {
        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(Carbon::now());
        $account->shouldReceive('getIsManuallyVerified')->andReturn(true);

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->eligibilityService->shouldReceive('checkTierLimits')->andReturn(null);
    }
}
