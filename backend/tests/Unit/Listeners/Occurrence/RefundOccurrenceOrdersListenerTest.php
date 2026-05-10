<?php

namespace Tests\Unit\Listeners\Occurrence;

use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;
use HiEvents\Listeners\Occurrence\RefundOccurrenceOrdersListener;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RefundOccurrenceOrdersListenerTest extends TestCase
{
    public function test_dispatches_refund_job_when_refund_orders_true(): void
    {
        Bus::fake();

        (new RefundOccurrenceOrdersListener)->handle(new OccurrenceCancelledEvent(
            eventId: 5,
            occurrenceId: 42,
            refundOrders: true,
        ));

        Bus::assertDispatched(
            RefundOccurrenceOrdersJob::class,
            fn (RefundOccurrenceOrdersJob $job) => $job->eventId === 5 && $job->occurrenceId === 42,
        );
    }

    public function test_does_not_dispatch_when_refund_orders_false(): void
    {
        Bus::fake();

        (new RefundOccurrenceOrdersListener)->handle(new OccurrenceCancelledEvent(
            eventId: 5,
            occurrenceId: 42,
            refundOrders: false,
        ));

        Bus::assertNotDispatched(RefundOccurrenceOrdersJob::class);
    }
}
