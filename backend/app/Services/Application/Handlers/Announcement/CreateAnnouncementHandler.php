<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\UpsertAnnouncementDTO;

class CreateAnnouncementHandler
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
        private readonly AnnouncementPayloadNormaliser $normaliser,
    ) {}

    public function handle(UpsertAnnouncementDTO $dto): AnnouncementDomainObject
    {
        return $this->announcementRepository->create($this->normaliser->normalise($dto));
    }
}
