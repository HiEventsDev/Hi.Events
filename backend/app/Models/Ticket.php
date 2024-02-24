<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use HiEvents\DomainObjects\Generated\TicketDomainObjectAbstract;

class Ticket extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            TicketDomainObjectAbstract::SALES_VOLUME => 'float',
            TicketDomainObjectAbstract::SALES_TAX_VOLUME => 'float',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'ticket_questions');
    }

    public function ticket_prices(): HasMany
    {
        return $this->hasMany(TicketPrice::class)->orderBy('order');
    }

    public function tax_and_fees(): BelongsToMany
    {
        return $this->belongsToMany(TaxAndFee::class, 'ticket_taxes_and_fees');
    }
}
