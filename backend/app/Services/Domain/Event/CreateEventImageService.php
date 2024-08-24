<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\EventImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Domain\Image\ImageUploadService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Throwable;

class CreateEventImageService
{
    public function __construct(
        private readonly ImageUploadService       $imageUploadService,
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly DatabaseManager          $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function createImage(
        int            $eventId,
        UploadedFile   $image,
        EventImageType $type,
    ): ImageDomainObject
    {
        return $this->databaseManager->transaction(function () use ($image, $eventId, $type) {
            if ($type === EventImageType::EVENT_COVER) {
                $this->imageRepository->deleteWhere([
                    'entity_id' => $eventId,
                    'entity_type' => EventDomainObject::class,
                    'type' => EventImageType::EVENT_COVER->name,
                ]);
            }

            return $this->imageUploadService->upload(
                image: $image,
                entityId: $eventId,
                entityType: EventDomainObject::class,
                imageType: EventImageType::EVENT_COVER->name,
            );
        });
    }
}
