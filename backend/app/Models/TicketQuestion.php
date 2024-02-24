<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\TicketQuestionDomainObjectAbstract;

class TicketQuestion extends BaseModel
{
    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [
            TicketQuestionDomainObjectAbstract::QUESTION_ID,
            TicketQuestionDomainObjectAbstract::TICKET_ID,
        ];
    }
}
