<?php

namespace Tests\Unit\Services\Application\Handlers\TicketLookup;

use Carbon\Carbon;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\TicketLookupTokenDomainObject;
use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketLookupTokenRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketLookup\DTO\GetOrdersByLookupTokenDTO;
use HiEvents\Services\Application\Handlers\TicketLookup\GetOrdersByLookupTokenHandler;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class GetOrdersByLookupTokenHandlerTest extends TestCase
{
    private TicketLookupTokenRepositoryInterface $ticketLookupTokenRepository;
    private OrderRepositoryInterface $orderRepository;
    private GetOrdersByLookupTokenHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ticketLookupTokenRepository = m::mock(TicketLookupTokenRepositoryInterface::class);
        $this->orderRepository = m::mock(OrderRepositoryInterface::class);

        $this->handler = new GetOrdersByLookupTokenHandler(
            $this->ticketLookupTokenRepository,
            $this->orderRepository,
        );
    }

    public function testHandleSuccessfullyReturnsOrdersWhenTokenIsValid(): void
    {
        $token = 'tl_validtoken123';
        $email = 'test@example.com';
        $dto = new GetOrdersByLookupTokenDTO(token: $token);

        $tokenRecord = m::mock(TicketLookupTokenDomainObject::class);
        $tokenRecord->shouldReceive('getExpiresAt')
            ->andReturn(Carbon::now()->addHours(12)->toDateTimeString());
        $tokenRecord->shouldReceive('getEmail')
            ->andReturn($email);

        $order = m::mock(OrderDomainObject::class);
        $orders = new Collection([$order]);

        $this->ticketLookupTokenRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['token' => $token])
            ->andReturn($tokenRecord);

        $this->orderRepository
            ->shouldReceive('loadRelation')
            ->andReturnSelf();

        $this->orderRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn($orders);

        $result = $this->handler->handle($dto);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function testHandleThrowsExceptionWhenTokenNotFound(): void
    {
        $token = 'tl_invalidtoken';
        $dto = new GetOrdersByLookupTokenDTO(token: $token);

        $this->ticketLookupTokenRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['token' => $token])
            ->andReturn(null);

        $this->orderRepository
            ->shouldNotReceive('findWhere');

        $this->expectException(InvalidTicketLookupTokenException::class);
        $this->expectExceptionMessage('Invalid or expired link. Please request a new one.');

        $this->handler->handle($dto);
    }

    public function testHandleThrowsExceptionWhenTokenIsExpired(): void
    {
        $token = 'tl_expiredtoken';
        $dto = new GetOrdersByLookupTokenDTO(token: $token);

        $tokenRecord = m::mock(TicketLookupTokenDomainObject::class);
        $tokenRecord->shouldReceive('getExpiresAt')
            ->andReturn(Carbon::now()->subHours(2)->toDateTimeString());

        $this->ticketLookupTokenRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['token' => $token])
            ->andReturn($tokenRecord);

        $this->orderRepository
            ->shouldNotReceive('findWhere');

        $this->expectException(InvalidTicketLookupTokenException::class);
        $this->expectExceptionMessage('This link has expired. Please request a new one.');

        $this->handler->handle($dto);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
