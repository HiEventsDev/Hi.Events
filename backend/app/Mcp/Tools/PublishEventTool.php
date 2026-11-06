<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\EventStatus;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('publish_event')]
#[Title('Publish event')]
#[Description('Makes an event public so people can view it and buy tickets. The account must have a verified email address. Hi.Events may hold an event for manual review if its content looks like spam.')]
#[IsDestructive]
class PublishEventTool extends EventStatusTool
{
    protected function status(): EventStatus
    {
        return EventStatus::LIVE;
    }
}
