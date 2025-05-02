<?php

namespace Tests\Unit\Services\Application\Handlers\Message;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\MessageTypeEnum;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use HiEvents\Services\Application\Handlers\Message\SendMessageHandler;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Bus;
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

        $this->handler = new SendMessageHandler(
            $this->orderRepository,
            $this->attendeeRepository,
            $this->productRepository,
            $this->messageRepository,
            $this->accountRepository,
            $this->purifier,
            $this->config
        );
    }

    public function testThrowsIfAccountNotVerified(): void
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
            order_statuses: [],
            order_id: null,
            attendee_ids: [],
            product_ids: []
        );

        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(null);

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);

        $this->expectException(AccountNotVerifiedException::class);

        $this->handler->handle($dto);
    }

    public function testThrowsIfSaasModeEnabledAndNotManuallyVerified(): void
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
            order_statuses: [],
            order_id: null,
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

    public function testHandleCreatesMessageAndDispatchesJob(): void
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
            order_statuses: [],
            order_id: 5,
            attendee_ids: [10],
            product_ids: [20],
        );

        $account = m::mock(AccountDomainObject::class);
        $account->shouldReceive('getAccountVerifiedAt')->andReturn(Carbon::now());
        $account->shouldReceive('getIsManuallyVerified')->andReturn(true);

        $this->accountRepository->shouldReceive('findById')->with(1)->andReturn($account);
        $this->config->shouldReceive('get')->with('app.saas_mode_enabled')->andReturn(false);

        $this->purifier->shouldReceive('purify')->with('<p>Test</p>')->andReturn('<p>Test</p>');

        $attendee = new AttendeeDomainObject();
        $attendee->setId(10);

        $product = new ProductDomainObject();
        $product->setId(20);

        $order = new OrderDomainObject();
        $order->setId(5);

        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(collect([$attendee]));
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(collect([$product]));
        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);

        $message = m::mock(MessageDomainObject::class);
        $message->shouldReceive('getId')->andReturn(1);
        $message->shouldReceive('getOrderId')->andReturn(5);
        $message->shouldReceive('getAttendeeIds')->andReturn([10]);
        $message->shouldReceive('getProductIds')->andReturn([20]);

        $this->messageRepository->shouldReceive('create')->andReturn($message);

        Bus::fake();

        $result = $this->handler->handle($dto);

        $this->assertSame($message, $result);

        Bus::assertDispatched(SendMessagesJob::class);
    }
}
