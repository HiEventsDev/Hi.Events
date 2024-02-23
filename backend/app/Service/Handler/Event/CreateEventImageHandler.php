<?php

namespace TicketKitten\Service\Handler\Event;

use Illuminate\Database\DatabaseManager;
use Throwable;
use TicketKitten\DomainObjects\Enums\EventImageType;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\Http\DataTransferObjects\CreateEventImageDTO;
use TicketKitten\Repository\Interfaces\ImageRepositoryInterface;
use TicketKitten\Service\Common\Image\ImageUploadService;

readonly class CreateEventImageHandler
{
    public function __construct(
        private ImageUploadService       $imageUploadService,
        private ImageRepositoryInterface $imageRepository,
        private DatabaseManager          $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateEventImageDTO $imageData): ImageDomainObject
    {
        return $this->databaseManager->transaction(function () use ($imageData) {
            if ($imageData->type === EventImageType::EVENT_COVER) {
                $this->imageRepository->deleteWhere([
                    'entity_id' => $imageData->event_id,
                    'entity_type' => EventDomainObject::class,
                    'type' => EventImageType::EVENT_COVER->name,
                ]);
            }

            return $this->imageUploadService->upload(
                image: $imageData->image,
                entityId: $imageData->event_id,
                entityType: EventDomainObject::class,
                imageType: EventImageType::EVENT_COVER->name,
            );
        });
    }
}
