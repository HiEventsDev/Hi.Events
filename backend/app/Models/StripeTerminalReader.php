<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StripeTerminalReader extends BaseModel
{
    use SoftDeletes;

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }
}
