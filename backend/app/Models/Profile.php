<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends BaseModel
{
    use SoftDeletes;

    protected $table = 'profiles';

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function lineup_entries(): HasMany
    {
        return $this->hasMany(EventLineupEntry::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(related: Image::class, name: 'entity')
            ->orderByRaw('sort_order asc nulls last')
            ->orderBy('id');
    }

    protected function getCastMap(): array
    {
        return [
            ProfileDomainObjectAbstract::LINKS => 'array',
        ];
    }
}
