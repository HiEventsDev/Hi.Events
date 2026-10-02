<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeatMap extends BaseModel
{
    use SoftDeletes;

    protected $table = 'seat_maps';

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    protected function getCastMap(): array
    {
        return [
            SeatMapDomainObjectAbstract::LAYOUT => 'array',
        ];
    }
}
