<?php

namespace HiEvents\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OccurrenceCancelledEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int  $eventId,
        public readonly int  $occurrenceId,
        public readonly bool $refundOrders = false,
    )
    {
    }
}
