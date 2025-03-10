<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookLog extends BaseModel
{
    use SoftDeletes;

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
