<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventSpamCheck extends BaseModel
{
    use SoftDeletes;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected function getCastMap(): array
    {
        return [
            'verdict' => 'array',
            'checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
