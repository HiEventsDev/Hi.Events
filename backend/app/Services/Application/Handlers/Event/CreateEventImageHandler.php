<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventImageDTO;
use HiEvents\Services\Domain\Event\CreateEventImageService;
use Throwable;

class CreateEventImageHandler
{
    public function __construct(
        private readonly CreateEventImageService $createEventImageService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateEventImageDTO $imageData): ImageDomainObject
    {
        return $this->createEventImageService->createImage(
            eventId: $imageData->eventId,
            accountId: $imageData->accountId,
            image: $imageData->image,
            imageType: $imageData->imageType,
        );
    }
}
