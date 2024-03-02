<?php

namespace HiEvents\Http\Actions\Events\Images;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\CreateEventImageRequest;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Services\Handlers\Event\CreateEventImageHandler;
use HiEvents\Services\Handlers\Event\DTO\CreateEventImageDTO;
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

        $image = $this->createEventImageHandler->handle(CreateEventImageDTO::fromArray($payload));

        return $this->resourceResponse(ImageResource::class, $image);
    }
}
