<?php

namespace HiEvents\Http\Actions\EventSettings;

use Illuminate\Http\JsonResponse;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\UpdateEventSettingsDTO;
use HiEvents\Http\Request\EventSettings\UpdateEventSettingsRequest;
use HiEvents\Resources\Event\EventSettingsResource;
use HiEvents\Service\Handler\EventSettings\UpdateEventSettingsHandler;

class EditEventSettingsAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventSettingsHandler $updateEventSettingsHandler
    )
    {
    }

    public function __invoke(UpdateEventSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $settings = array_merge(
            $request->validated(),
            [
                'event_id' => $eventId,
                'account_id' => $this->getAuthenticatedUser()->getAccountId(),
            ],
        );

        $event = $this->updateEventSettingsHandler->handle(
            UpdateEventSettingsDTO::fromArray($settings),
        );

        return $this->resourceResponse(EventSettingsResource::class, $event);
    }
}
