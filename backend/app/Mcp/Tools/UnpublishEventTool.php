<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\EventStatus;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('unpublish_event')]
#[Title('Unpublish event')]
#[Description('Moves an event back to draft. Its public page stops selling tickets; existing orders and tickets are kept.')]
#[IsDestructive]
class UnpublishEventTool extends EventStatusTool
{
    protected function status(): EventStatus
    {
        return EventStatus::DRAFT;
    }
}
