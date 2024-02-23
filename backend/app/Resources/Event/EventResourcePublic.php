<?php

namespace TicketKitten\Resources\Event;

use Illuminate\Http\Request;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Resources\BaseResource;
use TicketKitten\Resources\Image\ImageResource;
use TicketKitten\Resources\Question\QuestionResource;
use TicketKitten\Resources\Ticket\TicketResourcePublic;

/**
 * @mixin EventDomainObject
 */
class EventResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'description_preview' => $this->getDescriptionPreview(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'currency' => $this->getCurrency(),
            'slug' => $this->getSlug(),
            'status' => $this->getStatus(),
            'location_details' => $this->when((bool)$this->getLocationDetails(), fn() => $this->getLocationDetails()),

            'tickets' => $this->when(
                !is_null($this->getTickets()),
                fn() => TicketResourcePublic::collection($this->getTickets())
            ),
            'settings' => $this->when(
                !is_null($this->getEventSettings()),
                fn() => new EventSettingsResourcePublic($this->getEventSettings())
            ),
            // @TODO - public question resource
            'questions' => $this->when(
                !is_null($this->getQuestions()),
                fn() => QuestionResource::collection($this->getQuestions())
            ),
            'attributes' => $this->when(
                !is_null($this->getAttributes()),
                fn() => collect($this->getAttributes())->reject(fn($attribute) => !$attribute['is_public'])),
            'images' => $this->when(
                !is_null($this->getImages()),
                fn() => ImageResource::collection($this->getImages())
            ),
        ];
    }
}
