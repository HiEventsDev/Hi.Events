<?php

namespace HiEvents\Http\Actions\Messages;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Request\Message\PreviewMessageRequest;
use HiEvents\Services\Application\Handlers\Message\DTO\PreviewMessageDTO;
use HiEvents\Services\Application\Handlers\Message\PreviewMessageHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[AllowedForReadOnlyUsers]
class PreviewMessageAction extends BaseAction
{
    public function __construct(
        private readonly PreviewMessageHandler $handler,
    ) {}

    public function __invoke(PreviewMessageRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $preview = $this->handler->handle(new PreviewMessageDTO(
                account_id: $this->getAuthenticatedAccountId(),
                event_id: $eventId,
                user_id: $this->getAuthenticatedUser()->getId(),
                subject: $request->validated('subject'),
                message: $request->validated('message'),
            ));
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($preview->toArray(), wrapInData: true);
    }
}
