<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutgoingMessage extends BaseModel
{
    use SoftDeletes;

    public function email_delivery(): HasOne
    {
        return $this->hasOne(EmailDelivery::class, 'provider_message_id', 'provider_message_id');
    }
}
