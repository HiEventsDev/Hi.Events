<?php

namespace HiEvents\Resources\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnnouncementDomainObject
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'content' => $this->getContent(),
            'display_type' => $this->getDisplayType(),
            'emoji' => $this->getEmoji(),
            'cta_label' => $this->getCtaLabel(),
            'cta_url' => $this->getCtaUrl(),
        ];
    }
}
