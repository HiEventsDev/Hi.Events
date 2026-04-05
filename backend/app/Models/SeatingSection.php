<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingSection extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'position' => 'array',
        ];
    }

    public function seatingChart(): BelongsTo
    {
        return $this->belongsTo(SeatingChart::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class, 'section_id');
    }
}
