<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\CancelBoxOfficeOrderPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelBoxOfficeOrderPublicHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeOrderLookupService $lookup;

    private MockInterface|OrderCancelService $cancelService;

    private MockInterface|OrderAuditLogService $auditLog;

    private CancelBoxOfficeOrderPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lookup = Mockery::mock(BoxOfficeOrderLookupService::class);
        $this->cancelService = Mockery::mock(OrderCancelService::class);
        $this->auditLog = Mockery::mock(OrderAuditLogService::class);

        $this->handler = new CancelBoxOfficeOrderPublicHandler($this->lookup, $this->cancelService, $this->auditLog);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function order(string $tender, int $minutesAgo, string $status = OrderStatus::COMPLETED->name): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(5)
            ->setEventId(7)
            ->setStatus($status)
            ->setBoxOfficeTender($tender)
            ->setBoxOfficeCompletedAt(now()->subMinutes($minutesAgo)->toDateTimeString())
            ->setUpdatedAt(now()->subMinutes($minutesAgo)->toDateTimeString());
    }

    private function boxOffice(): BoxOfficeDomainObject
    {
        return (new BoxOfficeDomainObject)->setId(1)->setEventId(7);
    }

    public function test_voids_a_recent_cash_sale(): void
    {
        $order = $this->order(BoxOfficeTender::CASH->value, 5);
        $this->lookup->shouldReceive('findOrFail')->twice()->andReturn($order);
        $this->cancelService->shouldReceive('cancelOrder')->once()->with($order);
        $this->auditLog->shouldReceive('logBoxOfficeAction')->once();

        $result = $this->handler->handle($this->boxOffice(), 'o_1', 'Sam', '127.0.0.1', null);

        $this->assertSame(5, $result->getId());
    }

    public function test_refuses_card_sales(): void
    {
        $this->lookup->shouldReceive('findOrFail')->andReturn($this->order(BoxOfficeTender::CARD->value, 5));
        $this->cancelService->shouldNotReceive('cancelOrder');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->boxOffice(), 'o_1', 'Sam', '127.0.0.1', null);
    }

    public function test_refuses_sales_outside_the_void_window(): void
    {
        $this->lookup->shouldReceive('findOrFail')->andReturn($this->order(BoxOfficeTender::CASH->value, 45));
        $this->cancelService->shouldNotReceive('cancelOrder');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->boxOffice(), 'o_1', 'Sam', '127.0.0.1', null);
    }

    public function test_refuses_orders_that_are_not_completed(): void
    {
        $this->lookup->shouldReceive('findOrFail')->andReturn($this->order(BoxOfficeTender::CASH->value, 1, OrderStatus::RESERVED->name));

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->boxOffice(), 'o_1', 'Sam', '127.0.0.1', null);
    }

    public function test_a_later_edit_to_the_order_does_not_reopen_the_void_window(): void
    {
        $order = $this->order(BoxOfficeTender::CASH->value, 45)->setUpdatedAt(now()->subMinute()->toDateTimeString());
        $this->lookup->shouldReceive('findOrFail')->andReturn($order);
        $this->cancelService->shouldNotReceive('cancelOrder');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->boxOffice(), 'o_1', 'Sam', '127.0.0.1', null);
    }

    public function test_a_sale_without_a_door_completion_time_cannot_be_voided_at_the_door(): void
    {
        $order = $this->order(BoxOfficeTender::CASH->value, 1)->setBoxOfficeCompletedAt(null);
        $this->lookup->shouldReceive('findOrFail')->andReturn($order);
        $this->cancelService->shouldNotReceive('cancelOrder');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->boxOffice(), 'o_1', 'Sam', '127.0.0.1', null);
    }
}
