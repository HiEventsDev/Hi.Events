<?php

namespace Tests\Unit\Services\Application\Handlers\TicketLookup;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Mail\TicketLookup\TicketLookupEmail;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketLookupTokenRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketLookup\DTO\SendTicketLookupEmailDTO;
use HiEvents\Services\Application\Handlers\TicketLookup\SendTicketLookupEmailHandler;
use HiEvents\Services\Infrastructure\TokenGenerator\TokenGeneratorService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class SendTicketLookupEmailHandlerTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;
    private TicketLookupTokenRepositoryInterface $ticketLookupTokenRepository;
    private TokenGeneratorService $tokenGeneratorService;
    private Mailer $mailer;
    private LoggerInterface $logger;
    private DatabaseManager $databaseManager;
    private SendTicketLookupEmailHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = m::mock(OrderRepositoryInterface::class);
        $this->ticketLookupTokenRepository = m::mock(TicketLookupTokenRepositoryInterface::class);
        $this->tokenGeneratorService = m::mock(TokenGeneratorService::class);
        $this->mailer = m::mock(Mailer::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->databaseManager = m::mock(DatabaseManager::class);

        $this->handler = new SendTicketLookupEmailHandler(
            $this->orderRepository,
            $this->ticketLookupTokenRepository,
            $this->tokenGeneratorService,
            $this->mailer,
            $this->logger,
            $this->databaseManager,
        );
    }

    public function testHandleSuccessfullySendsEmailWhenOrdersExist(): void
    {
        $email = 'test@example.com';
        $dto = new SendTicketLookupEmailDTO(email: $email);
        $token = 'tl_test123';

        $order = m::mock(OrderDomainObject::class);
        $orders = new Collection([$order]);

        $this->orderRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn($orders);

        $this->databaseManager
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->ticketLookupTokenRepository
            ->shouldReceive('deleteWhere')
            ->once()
            ->with(['email' => $email]);

        $this->tokenGeneratorService
            ->shouldReceive('generateToken')
            ->once()
            ->andReturn($token);

        $this->ticketLookupTokenRepository
            ->shouldReceive('create')
            ->once();

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('Sending ticket lookup email', m::any());

        $pendingMail = m::mock('PendingMail');
        $this->mailer
            ->shouldReceive('to')
            ->once()
            ->with($email)
            ->andReturn($pendingMail);

        $pendingMail
            ->shouldReceive('send')
            ->once()
            ->with(m::type(TicketLookupEmail::class));

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleDoesNotSendEmailWhenNoOrdersExist(): void
    {
        $email = 'test@example.com';
        $dto = new SendTicketLookupEmailDTO(email: $email);

        $this->orderRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection());

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('Ticket lookup requested for email with no orders', ['email' => $email]);

        $this->databaseManager
            ->shouldNotReceive('transaction');

        $this->ticketLookupTokenRepository
            ->shouldNotReceive('create');

        $this->mailer
            ->shouldNotReceive('to');

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    public function testHandleConvertsEmailToLowercase(): void
    {
        $email = 'TEST@EXAMPLE.COM';
        $expectedLowercaseEmail = 'test@example.com';
        $dto = new SendTicketLookupEmailDTO(email: $email);

        $this->orderRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(new Collection());

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('Ticket lookup requested for email with no orders', ['email' => $expectedLowercaseEmail]);

        $this->handler->handle($dto);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
