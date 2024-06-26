<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Resources\Organizer\OrganizerResourcePublic;
use HiEvents\Resources\Question\QuestionResource;
use HiEvents\Resources\Ticket\TicketResourcePublic;
use Illuminate\Http\Request;

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
            'lifecycle_status' => $this->getLifecycleStatus(),
            'timezone' => $this->getTimezone(),
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
            'organizer' => $this->when(
                !is_null($this->getOrganizer()),
                fn() => new OrganizerResourcePublic($this->getOrganizer()),
            ),
        ];
    }
}
