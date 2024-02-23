<?php

namespace TicketKitten\Resources\Question;

use Illuminate\Http\Request;
use TicketKitten\DomainObjects\QuestionDomainObject;
use TicketKitten\Resources\BaseResource;
use TicketKitten\Resources\Ticket\TicketResourcePublic;

/**
 * @mixin QuestionDomainObject
 */
class QuestionResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'title' => $this->getTitle(),
            'options' => $this->getOptions(),
            'required' => $this->getRequired(),
            'event_id' => $this->getEventId(),
            'belongs_to' => $this->getBelongsTo(),
            'ticket_ids' => $this->when(
                !is_null($this->getTickets()),
                fn() => $this->getTickets()->map(fn($ticket) => $ticket->getId())
            ),
        ];
    }
}
