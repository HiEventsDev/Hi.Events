<?php

namespace TicketKitten\Http\Actions\Events\Images;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\Enums\EventImageType;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\ImageRepositoryInterface;
use TicketKitten\Resources\Image\ImageResource;

class GetEventImagesAction extends BaseAction
{
    public function __construct(private readonly ImageRepositoryInterface $imageRepository)
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $images = $this->imageRepository->findWhere([
            'entity_id' => $eventId,
            'entity_type' => EventDomainObject::class,
            'type' => EventImageType::EVENT_COVER->name,
        ]);

        return $this->resourceResponse(ImageResource::class, $images);
    }
}
