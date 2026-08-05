<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends BaseModel
{
    use SoftDeletes;

    protected $table = 'locations';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    protected function getCastMap(): array
    {
        return [
            LocationDomainObjectAbstract::STRUCTURED_ADDRESS => 'array',
            LocationDomainObjectAbstract::LATITUDE => 'float',
            LocationDomainObjectAbstract::LONGITUDE => 'float',
            LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE => 'array',
        ];
    }
}
