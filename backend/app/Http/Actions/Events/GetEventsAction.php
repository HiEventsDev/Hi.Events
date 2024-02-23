<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\EventSettingDomainObject;
use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Resources\Event\EventResource;

class GetEventsAction extends BaseAction
{
    public function __construct(private readonly EventRepositoryInterface $eventRepository)
    {
    }

    /**
     * @todo Move to handler
     */
    public function __invoke(Request $request): JsonResponse
    {
        $events = $this->eventRepository
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer',
            ))
            ->findEvents(
                where: [
                    'account_id' => $this->getAuthenticatedUser()->getAccountId(),
                ],
                params: QueryParamsDTO::fromArray($request->query->all())
            );

        return $this->filterableResourceResponse(
            resource: EventResource::class,
            data: $events,
            domainObject: EventDomainObject::class,
        );
    }
}
