<?php

namespace HiEvents\Services\Infrastructure\DomainEvents\Events;

use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;

class OccurrenceEvent extends BaseDomainEvent
{
    public function __construct(
        public DomainEventType $type,
        public int             $occurrenceId,
    )
    {
    }
}
