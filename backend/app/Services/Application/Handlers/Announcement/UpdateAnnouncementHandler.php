<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\UpsertAnnouncementDTO;

class UpdateAnnouncementHandler
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
        private readonly AnnouncementPayloadNormaliser $normaliser,
    ) {}

    /**
     * @throws AnnouncementNotFoundException
     */
    public function handle(UpsertAnnouncementDTO $dto): AnnouncementDomainObject
    {
        if ($this->announcementRepository->findFirstWhere(['id' => $dto->announcementId]) === null) {
            throw new AnnouncementNotFoundException(__('Announcement not found'));
        }

        return $this->announcementRepository->updateFromArray(
            $dto->announcementId,
            $this->normaliser->normalise($dto),
        );
    }
}
