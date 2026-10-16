<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\Exceptions\InvalidWidgetSettingsException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Widget\UpsertEventWidgetRequest;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Resources\Widget\WidgetResource;
use HiEvents\Services\Application\Handlers\Widget\DTO\UpsertWidgetDTO;
use HiEvents\Services\Application\Handlers\Widget\UpsertWidgetHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

abstract class BaseUpsertEventWidgetAction extends BaseAction
{
    public function __construct(
        private readonly UpsertWidgetHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @throws ValidationException
     * @throws ResourceNotFoundException
     */
    protected function upsert(UpsertEventWidgetRequest $request, int $eventId, ?int $widgetId, int $statusCode): JsonResponse
    {
        $event = $this->eventRepository->findById($eventId);

        try {
            $widget = $this->handler->handle(new UpsertWidgetDTO(
                account_id: $event->getAccountId(),
                organizer_id: $event->getOrganizerId(),
                event_id: $eventId,
                name: $request->validated('name'),
                type: $request->widgetType(),
                settings: $request->validated('settings') ?? [],
                widget_id: $widgetId,
            ));
        } catch (InvalidWidgetSettingsException $exception) {
            throw ValidationException::withMessages([$exception->getField() => $exception->getMessage()]);
        }

        return $this->resourceResponse(
            resource: WidgetResource::class,
            data: $widget,
            statusCode: $statusCode,
        );
    }
}
