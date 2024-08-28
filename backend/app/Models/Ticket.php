<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\TicketDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function capacity_assignments(): BelongsToMany
    {
        return $this->belongsToMany(CapacityAssignment::class, 'ticket_capacity_assignments');
    }

    public function check_in_lists(): BelongsToMany
    {
        return $this->belongsToMany(CheckInList::class, 'ticket_check_in_lists');
    }
}
