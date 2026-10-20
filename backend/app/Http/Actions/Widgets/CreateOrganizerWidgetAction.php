<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Request\Widget\UpsertOrganizerWidgetRequest;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

class CreateOrganizerWidgetAction extends BaseUpsertOrganizerWidgetAction
{
    public function __invoke(UpsertOrganizerWidgetRequest $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->upsert($request, $organizerId, null, ResponseCodes::HTTP_CREATED);
    }
}
