<?php

namespace HiEvents\Models;

class CrmPendingSubscription extends BaseModel
{
    protected function getTimestampsEnabled(): bool
    {
        return false;
    }
}
