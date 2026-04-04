<?php

namespace Tests\Unit\Jobs\Occurrence;

use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RefundOccurrenceOrdersJobTest extends TestCase
{
    private RefundOrderHandler|Mockery\MockInterface $refundHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundHandler = Mockery::mock(RefundOrderHandler::class);
    }

    private function mockDbChain(int $occurrenceId, array $orderIds, array $refundableOrders, array $multiOccurrenceOrderIds = []): void
    {
        $orderItemsBuilder = Mockery::mock('orderItemsBuilder');
        $ordersBuilder = Mockery::mock('ordersBuilder');
        $batchBuilder = Mockery::mock('batchBuilder');

        // First call: get order_ids for occurrence
        // Third call: batch multi-occurrence check
        DB::shouldReceive('table')->with('order_items')->andReturn($orderItemsBuilder, $batchBuilder);
        DB::shouldReceive('table')->with('orders')->andReturn($ordersBuilder);

        // First order_items query: get order IDs
        $orderItemsBuilder->shouldReceive('where')->with('event_occurrence_id', $occurrenceId)->andReturnSelf();
        $orderItemsBuilder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('distinct')->andReturnSelf();
        $orderItemsBuilder->shouldReceive('pluck')->with('order_id')->andReturn(collect($orderIds));

        // Orders query
        $ordersBuilder->shouldReceive('whereIn')->with('id', Mockery::any())->andReturnSelf();
        $ordersBuilder->shouldReceive('where')->with('status', 'COMPLETED')->andReturnSelf();
        $ordersBuilder->shouldReceive('where')->with('payment_status', 'PAYMENT_RECEIVED')->andReturnSelf();
        $ordersBuilder->shouldReceive('get')->with(['id', 'total_gross', 'currency'])->andReturn(
            collect(array_map(fn($o) => (object) $o, $refundableOrders))
        );

        // Batch multi-occurrence check
        $batchBuilder->shouldReceive('whereIn')->andReturnSelf();
        $batchBuilder->shouldReceive('whereNull')->andReturnSelf();
        $batchBuilder->shouldReceive('select')->andReturnSelf();
        $batchBuilder->shouldReceive('selectRaw')->andReturnSelf();
        $batchBuilder->shouldReceive('groupBy')->andReturnSelf();
        $batchBuilder->shouldReceive('havingRaw')->andReturnSelf();
        $batchBuilder->shouldReceive('pluck')->with('order_id')->andReturn(collect($multiOccurrenceOrderIds));
    }

    public function testHandleRefundsSingleOccurrenceOrders(): void
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
            ->with(Mockery::on(fn(RefundOrderDTO $dto) => $dto->event_id === 1
                && $dto->order_id === 100
                && $dto->amount === 50.00
                && $dto->notify_buyer === true
                && $dto->cancel_order === true
            ));

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler);

        $this->assertTrue(true);
    }

    public function testHandleSkipsMultiOccurrenceOrders(): void
    {
        Log::shouldReceive('warning')->once();

        $this->mockDbChain(
            occurrenceId: 10,
            orderIds: [100],
            refundableOrders: [['id' => 100, 'total_gross' => 50.00, 'currency' => 'USD']],
            multiOccurrenceOrderIds: [100],
        );

        $this->refundHandler->shouldNotReceive('handle');

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler);

        $this->assertTrue(true);
    }

    public function testHandleReturnsEarlyWhenNoOrderItems(): void
    {
        $builder = Mockery::mock('builder');
        DB::shouldReceive('table')->with('order_items')->andReturn($builder);
        $builder->shouldReceive('where')->with('event_occurrence_id', 10)->andReturnSelf();
        $builder->shouldReceive('whereNull')->with('deleted_at')->andReturnSelf();
        $builder->shouldReceive('distinct')->andReturnSelf();
        $builder->shouldReceive('pluck')->with('order_id')->andReturn(collect());

        $this->refundHandler->shouldNotReceive('handle');

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler);

        $this->assertTrue(true);
    }

    public function testHandleContinuesOnRefundError(): void
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

        $job = new RefundOccurrenceOrdersJob(1, 10);
        $job->handle($this->refundHandler);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
