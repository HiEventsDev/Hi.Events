<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventImageType;
use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Handlers\Event\DTO\DeleteEventImageDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteEventImageHandler
{
    public function __construct(
        private readonly ImageRepositoryInterface         $imageRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly DatabaseManager                  $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(DeleteEventImageDTO $deleteEventImageDTO): void
    {
        $this->databaseManager->beginTransaction();

        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $deleteEventImageDTO->eventId,
        ]);

        // If we're deleting the cover image, we need to reset the homepage background type to color
        if ($eventSettings?->getHomepageBackgroundType() === HomepageBackgroundType::MIRROR_COVER_IMAGE->name) {
            $this->eventSettingsRepository->updateWhere(attributes: [
                'homepage_background_type' => HomepageBackgroundType::COLOR->name,
            ], where: [
                'event_id' => $deleteEventImageDTO->eventId,
            ]);
        }

        $this->imageRepository->deleteWhere([
            'entity_id' => $deleteEventImageDTO->eventId,
            'entity_type' => EventDomainObject::class,
            'type' => EventImageType::EVENT_COVER->name,
            'id' => $deleteEventImageDTO->imageId,
        ]);

        $this->databaseManager->commit();
    }
}
