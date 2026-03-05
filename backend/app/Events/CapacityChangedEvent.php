<?php

namespace HiEvents\Events;

use HiEvents\DomainObjects\Enums\CapacityChangeDirection;

readonly class CapacityChangedEvent
{
    public function __construct(
        public int                     $eventId,
        public CapacityChangeDirection $direction,
        public ?int                    $productId = null,
        public ?int                    $productPriceId = null,
        public ?int                    $newCapacity = null,
    )
    {
    }
}
