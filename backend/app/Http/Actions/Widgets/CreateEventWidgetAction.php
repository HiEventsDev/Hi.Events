<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Request\Widget\UpsertEventWidgetRequest;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

class CreateEventWidgetAction extends BaseUpsertEventWidgetAction
{
    public function __invoke(UpsertEventWidgetRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->upsert($request, $eventId, null, ResponseCodes::HTTP_CREATED);
    }
}
