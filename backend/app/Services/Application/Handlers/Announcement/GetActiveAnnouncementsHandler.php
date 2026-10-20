<?php

namespace HiEvents\Services\Application\Handlers\Announcement;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use HiEvents\DomainObjects\Enums\AnnouncementDisplayType;
use HiEvents\Repository\Interfaces\AnnouncementRepositoryInterface;
use HiEvents\Repository\Interfaces\AnnouncementUserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Announcement\DTO\GetActiveAnnouncementsDTO;
use Illuminate\Support\Collection;

class GetActiveAnnouncementsHandler
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository,
        private readonly AnnouncementUserRepositoryInterface $announcementUserRepository,
    ) {}

    /**
     * @return Collection<int, AnnouncementDomainObject>
     */
    public function handle(GetActiveAnnouncementsDTO $dto): Collection
    {
        $eligible = $this->announcementRepository->findActiveForUser($dto->userId, $dto->accountId);

        $announcements = collect([
            $eligible->first(fn (AnnouncementDomainObject $announcement) => $announcement->getDisplayType() === AnnouncementDisplayType::BANNER->name),
            $eligible->first(fn (AnnouncementDomainObject $announcement) => $announcement->getDisplayType() === AnnouncementDisplayType::MODAL->name),
        ])->filter()->values();

        if ($announcements->isNotEmpty()) {
            $this->announcementUserRepository->markSeen(
                $announcements->map(fn (AnnouncementDomainObject $announcement) => $announcement->getId())->all(),
                $dto->userId,
            );
        }

        return $announcements;
    }
}
