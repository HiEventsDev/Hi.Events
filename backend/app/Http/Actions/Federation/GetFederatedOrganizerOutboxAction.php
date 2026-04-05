<?php

namespace HiEvents\Http\Actions\Federation;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Federation\ActivityPubTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetFederatedOrganizerOutboxAction extends BaseAction
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly ActivityPubTransformer       $transformer,
    )
    {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $organizer = $this->organizerRepository->findById($organizerId);
        $baseUrl = rtrim(config('app.url'), '/');

        $events = $this->eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findWhere([
                'organizer_id' => $organizerId,
                'status' => EventStatus::LIVE->name,
            ]);

        $actorId = sprintf('%s/federation/actors/organizers/%d', $baseUrl, $organizer->getId());

        $items = [];
        /** @var EventDomainObject $event */
        foreach ($events as $event) {
            $eventObject = $this->transformer->eventToObject(
                $event,
                $organizer,
                $event->getEventSettings(),
                $baseUrl,
            );
            $items[] = $this->transformer->wrapInCreateActivity($eventObject, $actorId);
        }

        $outbox = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorId . '/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => count($items),
            'orderedItems' => $items,
        ];

        return response()->json($outbox, 200, [
            'Content-Type' => 'application/activity+json',
        ]);
    }
}
