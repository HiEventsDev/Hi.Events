<?php

namespace HiEvents\Http\Actions\Events\Images;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\Enums\EventImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Resources\Image\ImageResource;

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
