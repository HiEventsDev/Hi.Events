<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSeatMap extends BaseModel
{
    protected $table = 'event_seat_maps';

    public function seat_map(): BelongsTo
    {
        return $this->belongsTo(SeatMap::class);
    }

    public function event_seat_map_band_products(): HasMany
    {
        return $this->hasMany(EventSeatMapBandProduct::class);
    }

    protected function getCastMap(): array
    {
        return [
            EventSeatMapDomainObjectAbstract::LAYOUT => 'array',
        ];
    }
}
