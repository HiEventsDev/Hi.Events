<?php

namespace TicketKitten\Http\Actions\Messages;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\MessageDomainObject;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\MessageRepositoryInterface;
use TicketKitten\Resources\Message\MessageResource;

class GetMessagesAction extends BaseAction
{
    private MessageRepositoryInterface $messageRepository;

    public function __construct(MessageRepositoryInterface $MessageRepository)
    {
        $this->messageRepository = $MessageRepository;
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $messages = $this->messageRepository
            ->loadRelation(new Relationship(UserDomainObject::class, name: 'sent_by_user'))
            ->findByEventId($eventId, QueryParamsDTO::fromArray($request->query->all()));

        return $this->filterableResourceResponse(
            resource: MessageResource::class,
            data: $messages,
            domainObject: MessageDomainObject::class
        );
    }
}
