<?php

namespace HiEvents\Http\Actions\Events\Images;

use HiEvents\DomainObjects\Enums\EventImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\CreateEventImageRequest;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Services\Application\Handlers\Event\CreateEventImageHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventImageDTO;
use Illuminate\Http\JsonResponse;

class CreateEventImageAction extends BaseAction
{
    private CreateEventImageHandler $createEventImageHandler;

    public function __construct(CreateEventImageHandler $createEventImageHandler)
    {
        $this->createEventImageHandler = $createEventImageHandler;
    }

    public function __invoke(CreateEventImageRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $payload = array_merge($request->validated(), [
            'event_id' => $eventId,
        ]);

        $image = $this->createEventImageHandler->handle(new CreateEventImageDTO(
            event_id: $payload['event_id'],
            image: $request->file('image'),
            imageType: EventImageType::fromName($payload['type']),
        ));

        return $this->resourceResponse(ImageResource::class, $image);
    }
}
