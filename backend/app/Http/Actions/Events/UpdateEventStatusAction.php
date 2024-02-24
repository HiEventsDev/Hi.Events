<?php

namespace HiEvents\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\UpdateEventStatusDTO;
use HiEvents\Http\Request\Event\UpdateEventStatusRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Service\Handler\Event\UpdateEventStatusHandler;

class UpdateEventStatusAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventStatusHandler $updateEventStatusHandler,
    )
    {
    }

    public function __invoke(UpdateEventStatusRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $updatedEvent = $this->updateEventStatusHandler->handle(UpdateEventStatusDTO::fromArray([
                'status' => $request->input('status'),
                'eventId' => $eventId,
                'accountId' => $this->getAuthenticatedUser()->getAccountId(),
            ]));
        } catch (AccountNotVerifiedException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->resourceResponse(EventResource::class, $updatedEvent);
    }
}
