<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\Exceptions\AnnouncementNotFoundException;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;

class DeleteAnnouncementHandler
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
    ) {}

    /**
     * @throws AnnouncementNotFoundException
     */
    public function handle(int $announcementId): void
    {
        if ($this->announcementRepository->findFirstWhere(['id' => $announcementId]) === null) {
            throw new AnnouncementNotFoundException(__('Announcement not found'));
        }

        $this->announcementRepository->deleteById($announcementId);
    }
}
