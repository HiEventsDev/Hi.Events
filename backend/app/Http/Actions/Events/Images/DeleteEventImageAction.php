<?php

namespace HiEvents\Http\Actions\Events\Images;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\Event\DeleteEventImageHandler;
use HiEvents\Services\Handlers\Event\DTO\DeleteEventImageDTO;
use Illuminate\Http\Response;

class DeleteEventImageAction extends BaseAction
{
    public function __construct(private readonly DeleteEventImageHandler $deleteEventImageHandler)
    {
    }

    public function __invoke(int $eventId, int $imageId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->deleteEventImageHandler->handle(new DeleteEventImageDTO(
            eventId: $eventId,
            imageId: $imageId,
        ));

        return $this->deletedResponse();
    }
}
