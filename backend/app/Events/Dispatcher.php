<?php

declare(strict_types=1);

namespace HiEvents\Events;

use Illuminate\Bus\Dispatcher as QueueDispatcher;
use Illuminate\Events\Dispatcher as EventDispatcher;

class Dispatcher
{
    private QueueDispatcher $jobDispatcher;

    private EventDispatcher $eventDispatcher;

    public function __construct(QueueDispatcher $jobDispatcher, EventDispatcher $eventDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function dispatchJob($command)
    {
        return $this->jobDispatcher->dispatchToQueue($command);
    }

    public function dispatchEvent($event, array $payload = []): ?array
    {
        return $this->eventDispatcher->dispatch($event, $payload);
    }
}
