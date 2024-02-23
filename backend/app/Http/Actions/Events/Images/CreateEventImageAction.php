<?php

namespace TicketKitten\Http\Actions\Events\Images;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CreateEventImageDTO;
use TicketKitten\Http\Request\Event\CreateEventImageRequest;
use TicketKitten\Resources\Image\ImageResource;
use TicketKitten\Service\Handler\Event\CreateEventImageHandler;

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
