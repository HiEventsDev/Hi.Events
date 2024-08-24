<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Services\Domain\Event\CreateEventImageService;
use HiEvents\Services\Handlers\Event\DTO\CreateEventImageDTO;
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
            eventId: $imageData->event_id,
            image: $imageData->image,
            type: $imageData->type,
        );
    }
}
