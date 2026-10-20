<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\Exceptions\InvalidWidgetSettingsException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Widget\UpsertOrganizerWidgetRequest;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Resources\Widget\WidgetResource;
use HiEvents\Services\Application\Handlers\Widget\DTO\UpsertWidgetDTO;
use HiEvents\Services\Application\Handlers\Widget\UpsertWidgetHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

abstract class BaseUpsertOrganizerWidgetAction extends BaseAction
{
    public function __construct(
        private readonly UpsertWidgetHandler $handler,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    /**
     * @throws ValidationException
     * @throws ResourceNotFoundException
     */
    protected function upsert(UpsertOrganizerWidgetRequest $request, int $organizerId, ?int $widgetId, int $statusCode): JsonResponse
    {
        $organizer = $this->organizerRepository->findById($organizerId);

        try {
            $widget = $this->handler->handle(new UpsertWidgetDTO(
                account_id: $organizer->getAccountId(),
                organizer_id: $organizerId,
                event_id: null,
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
