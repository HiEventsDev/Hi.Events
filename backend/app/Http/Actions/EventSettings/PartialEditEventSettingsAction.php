<?php

namespace HiEvents\Http\Actions\EventSettings;

use Illuminate\Http\JsonResponse;
use Throwable;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\PartialUpdateEventSettingsDTO;
use HiEvents\Http\Request\EventSettings\UpdateEventSettingsRequest;
use HiEvents\Resources\Event\EventSettingsResource;
use HiEvents\Service\Handler\EventSettings\PartialUpdateEventSettingsHandler;

class PartialEditEventSettingsAction extends BaseAction
{
    public function __construct(
        private readonly PartialUpdateEventSettingsHandler $partialUpdateEventSettingsHandler
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(UpdateEventSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->partialUpdateEventSettingsHandler->handle(
            PartialUpdateEventSettingsDTO::fromArray([
                'settings' => $request->validated(),
                'event_id' => $eventId,
                'account_id' => $this->getAuthenticatedUser()->getAccountId(),
            ]),
        );

        return $this->resourceResponse(EventSettingsResource::class, $event);
    }
}
