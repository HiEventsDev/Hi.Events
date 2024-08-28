<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Resources\Message\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
