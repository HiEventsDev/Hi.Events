<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\Status\AnnouncementStatus;
use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementUserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\DismissAnnouncementDTO;

class DismissAnnouncementHandler
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
        private readonly AnnouncementUserRepositoryInterface $announcementUserRepository,
    ) {}

    /**
     * @throws AnnouncementNotFoundException
     */
    public function handle(DismissAnnouncementDTO $dto): void
    {
        $announcement = $this->announcementRepository->findFirstWhere([
            'id' => $dto->announcementId,
            'status' => AnnouncementStatus::PUBLISHED->name,
        ]);

        if ($announcement === null) {
            throw new AnnouncementNotFoundException(__('Announcement not found'));
        }

        $this->announcementUserRepository->markDismissed($dto->announcementId, $dto->userId);
    }
}
