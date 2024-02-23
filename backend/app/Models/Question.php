<?php

namespace TicketKitten\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use TicketKitten\DomainObjects\Generated\QuestionDomainObjectAbstract;

class Question extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            QuestionDomainObjectAbstract::OPTIONS => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function tickets(): BelongsToMany
    {
        return $this
            ->belongsToMany(Ticket::class, 'ticket_questions')
            ->whereNull('ticket_questions.deleted_at');
    }
}
