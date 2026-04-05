<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeatingChart extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'layout' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SeatingSection::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }
}
