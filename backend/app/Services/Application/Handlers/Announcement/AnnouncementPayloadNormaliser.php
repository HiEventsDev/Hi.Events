<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\Enums\AnnouncementDisplayType;
use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\Generated\AnnouncementDomainObjectAbstract;
use HiEvents\Services\Application\Handlers\Announcement\DTO\UpsertAnnouncementDTO;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class AnnouncementPayloadNormaliser
{
    public function __construct(
        private readonly HtmlPurifierService $purifier,
    ) {}

    public function normalise(UpsertAnnouncementDTO $dto): array
    {
        $isModal = $dto->displayType === AnnouncementDisplayType::MODAL->name;

        return [
            AnnouncementDomainObjectAbstract::TITLE => $dto->title,
            AnnouncementDomainObjectAbstract::CONTENT => $isModal
                ? $this->purifier->purify($dto->content)
                : $dto->content,
            AnnouncementDomainObjectAbstract::STATUS => $dto->status,
            AnnouncementDomainObjectAbstract::DISPLAY_TYPE => $dto->displayType,
            AnnouncementDomainObjectAbstract::EMOJI => $isModal ? $dto->emoji : null,
            AnnouncementDomainObjectAbstract::TARGET_TYPE => $dto->targetType,
            AnnouncementDomainObjectAbstract::TARGET_ACCOUNT_IDS => $dto->targetType === AnnouncementTargetType::ACCOUNTS->name
                ? array_values(array_map('intval', $dto->targetAccountIds ?? []))
                : null,
            AnnouncementDomainObjectAbstract::TARGET_USER_IDS => $dto->targetType === AnnouncementTargetType::USERS->name
                ? array_values(array_map('intval', $dto->targetUserIds ?? []))
                : null,
            AnnouncementDomainObjectAbstract::CTA_LABEL => $dto->ctaLabel,
            AnnouncementDomainObjectAbstract::CTA_URL => $dto->ctaUrl,
        ];
    }
}
