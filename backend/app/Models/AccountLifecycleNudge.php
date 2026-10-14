<?php

declare(strict_types=1);

namespace HiEvents\Models;

class AccountLifecycleNudge extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'account_id',
            'nudge_type',
            'event_id',
            'provider_message_id',
        ];
    }
}
