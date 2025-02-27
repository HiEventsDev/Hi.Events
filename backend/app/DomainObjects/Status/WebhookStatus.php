<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum WebhookStatus: string
{
    use BaseEnum;

    case ENABLED = 'ENABLED';
    case PAUSED = 'PAUSED';
}
