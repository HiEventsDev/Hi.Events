<?php

namespace HiEvents\Services\Application\Handlers\Announcement\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertAnnouncementDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly string $status,
        public readonly string $displayType,
        public readonly string $targetType,
        public readonly ?string $emoji = null,
        public readonly ?array $targetAccountIds = null,
        public readonly ?array $targetUserIds = null,
        public readonly ?string $ctaLabel = null,
        public readonly ?string $ctaUrl = null,
        public readonly ?int $announcementId = null,
    ) {}
}
