<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
