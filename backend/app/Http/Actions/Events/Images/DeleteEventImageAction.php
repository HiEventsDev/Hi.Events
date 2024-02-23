<?php

namespace TicketKitten\Http\Actions\Events\Images;

use Illuminate\Http\Response;
use TicketKitten\DomainObjects\Enums\EventImageType;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\ImageRepositoryInterface;

class DeleteEventImageAction extends BaseAction
{
    public function __construct(private readonly ImageRepositoryInterface $imageRepository)
    {
    }

    public function __invoke(int $eventId, int $imageId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->imageRepository->deleteWhere([
            'entity_id' => $eventId,
            'entity_type' => EventDomainObject::class,
            'type' => EventImageType::EVENT_COVER->name,
            'id' => $imageId,
        ]);

        return $this->deletedResponse();
    }
}
