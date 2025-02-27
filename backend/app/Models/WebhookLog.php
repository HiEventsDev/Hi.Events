<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends BaseModel
{
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
