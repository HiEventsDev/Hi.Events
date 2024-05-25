<?php

namespace HiEvents\Http\Actions\EventSettings;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventSettings\UpdateEventSettingsRequest;
use HiEvents\Resources\Event\EventSettingsResource;
use HiEvents\Services\Handlers\EventSettings\DTO\UpdateEventSettingsDTO;
use HiEvents\Services\Handlers\EventSettings\UpdateEventSettingsHandler;
use Illuminate\Http\JsonResponse;

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
                'account_id' => $this->getAuthenticatedAccountId(),
            ],
        );

        $event = $this->updateEventSettingsHandler->handle(
            UpdateEventSettingsDTO::fromArray($settings),
        );

        return $this->resourceResponse(EventSettingsResource::class, $event);
    }
}
