<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Request\Widget\UpsertOrganizerWidgetRequest;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

class UpdateOrganizerWidgetAction extends BaseUpsertOrganizerWidgetAction
{
    public function __invoke(UpsertOrganizerWidgetRequest $request, int $organizerId, int $widgetId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->upsert($request, $organizerId, $widgetId, ResponseCodes::HTTP_OK);
    }
}
