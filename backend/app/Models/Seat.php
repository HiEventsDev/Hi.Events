<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'position' => 'array',
            'is_accessible' => 'boolean',
            'is_aisle' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatingSection::class, 'section_id');
    }

    public function seatingChart(): BelongsTo
    {
        return $this->belongsTo(SeatingChart::class, 'chart_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
