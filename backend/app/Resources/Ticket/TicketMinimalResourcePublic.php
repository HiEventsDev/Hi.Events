<?php

namespace HiEvents\Resources\Ticket;

use HiEvents\DomainObjects\TicketDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketDomainObject
 */
class TicketMinimalResourcePublic extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'event_id' => $this->getEventId(),
            'prices' => $this->when(
                (bool)$this->getTicketPrices(),
                fn() => TicketPriceResourcePublic::collection($this->getTicketPrices()),
            ),
        ];
    }
}
