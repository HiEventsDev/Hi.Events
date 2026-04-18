<?php

namespace HiEvents\Resources\Contact;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Resources\BaseResource;

/**
 * @mixin ContactDomainObject
 */
class ContactResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'email' => $this->getEmail(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'attributes' => $this->getAttributes(),
            'attributes_history' => $this->getAttributesHistory(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            $this->mergeWhen($this->getAttendees() !== null, fn() => [
                'attendees' => AttendeeResource::collection($this->getAttendees()),
            ]),
        ];
    }
}
