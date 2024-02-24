<?php

namespace HiEvents\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotChangeCurrencyException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\UpdateEventDTO;
use HiEvents\Http\Request\Event\UpdateEventRequest;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Service\Handler\Event\UpdateEventHandler;

class UpdateEventAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventHandler $updateEventHandler
    )
    {
    }

    /**
     * @throws Throwable|ValidationException
     */
    public function __invoke(UpdateEventRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $authorisedUser = $this->getAuthenticatedUser();

        try {
            $event = $this->updateEventHandler->handle(
                UpdateEventDTO::fromArray(
                    array_merge(
                        $request->validated(),
                        [
                            'id' => $eventId,
                            'account_id' => $authorisedUser->getAccountId(),
                            'user_id' => $authorisedUser->getId(),
                        ]
                    )
                )
            );
        } catch (CannotChangeCurrencyException $exception) {
            throw ValidationException::withMessages([
                'currency' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(EventResource::class, $event);
    }
}
