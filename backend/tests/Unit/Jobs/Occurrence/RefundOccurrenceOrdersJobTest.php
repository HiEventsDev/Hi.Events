<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\RefundOrderHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RefundOccurrenceOrdersJobTest extends TestCase
{
    private RefundOrderHandler|Mockery\MockInterface $refundHandler;

    private OrderAuditLogRepositoryInterface|Mockery\MockInterface $auditLogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundHandler = Mockery::mock(RefundOrderHandler::class);
        $this->auditLogRepository = Mockery::mock(OrderAuditLogRepositoryInterface::class);
    }

    private function mockDbChain(int $occurrenceId, array $orderIds, array $refundableOrders, array $multiOccurrenceOrderIds = []): void
    {
        $orderItemsBuilder = Mockery::mock('orderItemsBuilder');
        $ordersBuilder = Mockery::mock('ordersBuilder');
        $batchBuilder = Mockery::mock('batchBuilder');

        DB::shouldReceive('table')->with('order_items')->andReturn($orderItemsBuilder, $batchBuilder);
        DB::shouldReceive('table')->with('orders')->andReturn($ordersBuilder);

        $orderItemsBuilder->shouldReceive('where')->with('event_occurrence_id', $occurrenceId)->andReturnSelf();
        $orderItemsBuilder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('distinct')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('pluck')->with('order_id')->andReturn(collect($orderIds));

        $ordersBuilder->shouldReceive('whereIn')->with('id', Mockery::any())->andReturnSelf();
        $ordersBuilder->shouldReceive('where')->with('status', 'COMPLETED')->andReturnSelf();
        $ordersBuilder->shouldReceive('where')->with('payment_status', 'PAYMENT_RECEIVED')->andReturnSelf();
        $ordersBuilder->shouldReceive('whereNull')->with('refund_status')->andReturnSelf();
        $ordersBuilder->shouldReceive('get')->with(['id', 'total_gross', 'currency'])->andReturn(
            collect(array_map(fn ($o) => (object) $o, $refundableOrders))
        );

        $batchBuilder->shouldReceive('whereIn')->andReturnSelf();
        $batchBuilder->shouldReceive('whereNull')->andReturnSelf();
        $batchBuilder->shouldReceive('select')->andReturnSelf();
        $batchBuilder->shouldReceive('groupBy')->andReturnSelf();
        $batchBuilder->shouldReceive('havingRaw')->andReturnSelf();
        $batchBuilder->shouldReceive('pluck')->with('order_id')->andReturn(collect($multiOccurrenceOrderIds));
    }

    public function test_handle_refunds_single_occurrence_orders(): void
    {
        $this->mockDbChain(
            occurrenceId: 10,
            orderIds: [100],
            refundableOrders: [['id' => 100, 'total_gross' => 50.00, 'currency' => 'USD']],
            multiOccurrenceOrderIds: [],
        );

        $this->refundHandler
            ->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(fn (RefundOrderDTO $dto) => $dto->event_id === 1
                && $dto->order_id === 100
                && $dto->amount === 50.00
                && $dto->notify_buyer === true
                && $dto->cancel_order === true
            ));

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler, $this->auditLogRepository);

        $this->assertTrue(true);
    }

    public function test_handle_skips_multi_occurrence_orders(): void
    {
        Log::shouldReceive('warning')->once();

        $this->mockDbChain(
            occurrenceId: 10,
            orderIds: [100],
            refundableOrders: [['id' => 100, 'total_gross' => 50.00, 'currency' => 'USD']],
            multiOccurrenceOrderIds: [100],
        );

        $this->refundHandler->shouldNotReceive('handle');

        $this->auditLogRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $attributes) => $attributes['order_id'] === 100
                && $attributes['event_id'] === 1
                && $attributes['action'] === OrderAuditAction::AUTOMATIC_REFUND_SKIPPED->value
                && $attributes['new_values']['cancelled_occurrence_id'] === 10));

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler, $this->auditLogRepository);

        $this->assertTrue(true);
    }

    public function test_handle_returns_early_when_no_order_items(): void
    {
        $builder = Mockery::mock('builder');
        DB::shouldReceive('table')->with('order_items')->andReturn($builder);
        $builder->shouldReceive('where')->with('event_occurrence_id', 10)->andReturnSelf();
        $builder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        $builder->shouldReceive('distinct')->andReturnSelf();
        $builder->shouldReceive('pluck')->with('order_id')->andReturn(collect());

        $this->refundHandler->shouldNotReceive('handle');

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler, $this->auditLogRepository);

        $this->assertTrue(true);
    }

    public function test_handle_skips_orders_already_refunded(): void
    {
        $this->mockDbChain(
            occurrenceId: 10,
            orderIds: [100, 101],
            refundableOrders: [],
            multiOccurrenceOrderIds: [],
        );

        $this->refundHandler->shouldNotReceive('handle');

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler, $this->auditLogRepository);

        $this->assertTrue(true);
    }

    public function test_unique_id_is_occurrence_scoped(): void
    {
        $job = new RefundOccurrenceOrdersJob(1, 10);
        $this->assertSame('occurrence:10', $job->uniqueId());
    }

    public function test_handle_continues_on_refund_error_and_writes_audit_log(): void
    {
        Log::shouldReceive('error')->once();

        $this->mockDbChain(
            occurrenceId: 10,
            orderIds: [100],
            refundableOrders: [['id' => 100, 'total_gross' => 50.00, 'currency' => 'USD']],
            multiOccurrenceOrderIds: [],
        );

        $this->refundHandler
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new \RuntimeException('Stripe error'));

        $this->auditLogRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $attributes) => $attributes['order_id'] === 100
                && $attributes['event_id'] === 1
                && $attributes['action'] === OrderAuditAction::AUTOMATIC_REFUND_FAILED->value
                && $attributes['new_values']['cancelled_occurrence_id'] === 10
                && $attributes['new_values']['error'] === 'Stripe error'));

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler, $this->auditLogRepository);

        $this->assertTrue(true);
    }

    public function test_failed_logs_critical(): void
    {
        Log::shouldReceive('critical')
            ->once()
            ->with('RefundOccurrenceOrdersJob permanently failed after retries', Mockery::on(fn (array $context) => $context['event_id'] === 1
                && $context['occurrence_id'] === 10
                && $context['error'] === 'queue exhausted'));

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->failed(new \RuntimeException('queue exhausted'));

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
