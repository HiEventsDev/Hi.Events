<?php

namespace HiEvents\Listeners\Occurrence;

use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Jobs\Occurrence\SendOccurrenceCancellationEmailJob;

class SendOccurrenceCancellationNotification
{
    public function handle(OccurrenceCancelledEvent $event): void
    {
        dispatch(new SendOccurrenceCancellationEmailJob(
            eventId: $event->eventId,
            occurrenceId: $event->occurrenceId,
            refundOrders: $event->refundOrders,
        ));
    }
}
