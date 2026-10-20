<?php

namespace HiEvents\Listeners\Occurrence;

use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Jobs\Occurrence\RefundOccurrenceOrdersJob;

class RefundOccurrenceOrdersListener
{
    public function handle(OccurrenceCancelledEvent $event): void
    {
        if (! $event->refundOrders) {
            return;
        }

        dispatch(new RefundOccurrenceOrdersJob(
            eventId: $event->eventId,
            occurrenceId: $event->occurrenceId,
        ));
    }
}
