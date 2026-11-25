<?php

namespace HiEvents\Resources\TicketLookup;

use HiEvents\Services\Application\Handlers\TicketLookup\DTO\LookupTicketDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LookupTicketDTO
 */
class LookupTicketResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'short_id' => $this->attendee->getShortId(),
            'first_name' => $this->attendee->getFirstName(),
            'last_name' => $this->attendee->getLastName(),
            'product_title' => $this->attendee->getProduct()?->getTitle(),
            'event_id' => $this->event->getId(),
            'event_title' => $this->event->getTitle(),
            'event_timezone' => $this->event->getTimezone(),
            'start_date' => $this->attendee->getEventOccurrence()?->getStartDate(),
        ];
    }
}
