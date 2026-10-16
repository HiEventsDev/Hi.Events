<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLineupEntry extends BaseModel
{
    protected $table = 'event_lineup_entries';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    protected function getCastMap(): array
    {
        return [
            EventLineupEntryDomainObjectAbstract::IS_HIDDEN => 'boolean',
        ];
    }
}
