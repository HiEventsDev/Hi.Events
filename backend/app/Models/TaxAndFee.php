<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxAndFee extends BaseModel
{
    protected $table = 'taxes_and_fees';

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_taxes_and_fees');
    }

    protected function getCastMap(): array
    {
        return [
            'rate' => 'float',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}
