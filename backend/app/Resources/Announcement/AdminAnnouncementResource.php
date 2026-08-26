<?php

namespace HiEvents\Resources\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

class AdminAnnouncementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof AnnouncementDomainObject) {
            return [
                'id' => $this->resource->getId(),
                'title' => $this->resource->getTitle(),
                'content' => $this->resource->getContent(),
                'status' => $this->resource->getStatus(),
                'display_type' => $this->resource->getDisplayType(),
                'emoji' => $this->resource->getEmoji(),
                'target_type' => $this->resource->getTargetType(),
                'target_account_ids' => $this->resource->getTargetAccountIds(),
                'target_user_ids' => $this->resource->getTargetUserIds(),
                'cta_label' => $this->resource->getCtaLabel(),
                'cta_url' => $this->resource->getCtaUrl(),
                'created_at' => $this->resource->getCreatedAt(),
                'updated_at' => $this->resource->getUpdatedAt(),
                'target_names' => (object) [],
                'seen_count' => 0,
                'dismissed_count' => 0,
            ];
        }

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'content' => $this->resource->content,
            'status' => $this->resource->status,
            'display_type' => $this->resource->display_type,
            'emoji' => $this->resource->emoji,
            'target_type' => $this->resource->target_type,
            'target_account_ids' => $this->resource->target_account_ids,
            'target_user_ids' => $this->resource->target_user_ids,
            'cta_label' => $this->resource->cta_label,
            'cta_url' => $this->resource->cta_url,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'target_names' => (object) ($this->resource->target_names ?? []),
            'seen_count' => $this->resource->seen_count ?? 0,
            'dismissed_count' => $this->resource->dismissed_count ?? 0,
        ];
    }
}
