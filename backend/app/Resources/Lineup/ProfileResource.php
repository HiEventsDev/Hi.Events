<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Image\ImageResource;
use Illuminate\Http\Request;

/**
 * @mixin ProfileDomainObject
 */
class ProfileResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'organizer_id' => $this->getOrganizerId(),
            'name' => $this->getName(),
            'tagline' => $this->getTagline(),
            'bio' => $this->getBio(),
            'links' => $this->getLinks() ?? [],
            'images' => ImageResource::collection($this->getImages() ?? collect()),
            'appearances' => $this->when(
                $this->getLineupEntries() !== null,
                fn () => $this->getLineupEntries()
                    ->map(fn (EventLineupEntryDomainObject $entry) => [
                        'event_id' => $entry->getEventId(),
                        'event_title' => $entry->getEvent()?->getTitle(),
                        /** @var 'DRAFT'|'LIVE'|'ARCHIVED'|'PENDING_MANUAL_REVIEW' */
                        'event_status' => $entry->getEvent()?->getStatus(),
                        'billing_tier' => $entry->getBillingTier(),
                        'role_label' => $entry->getRoleLabel(),
                        'is_hidden' => $entry->getIsHidden(),
                    ])
                    ->values()
                    ->all(),
            ),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
