<?php

namespace HiEvents\Resources\Admin;

use HiEvents\DomainObjects\EventSpamCheckDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin EventSpamCheckDomainObject
 */
class AdminSpamEventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $event = $this->getEvent();

        return [
            'id' => $this->getId(),
            'event_id' => $this->getEventId(),
            'event_title' => $event?->getTitle(),
            'event_description' => trim(strip_tags($event?->getDescription() ?? '')),
            'organizer_name' => $event?->getOrganizer()?->getName(),
            'account_name' => $event?->getAccount()?->getName(),
            'account_email' => $event?->getAccount()?->getEmail(),
            'account_id' => $event?->getAccountId(),
            'verdict' => (object) ($this->getVerdict() ?? []),
            'checked_at' => $this->getCheckedAt(),
        ];
    }
}
