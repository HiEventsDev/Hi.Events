<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Widget\WidgetResource;
use HiEvents\Services\Application\Handlers\Widget\GetWidgetsHandler;
use Illuminate\Http\JsonResponse;

class GetOrganizerWidgetsAction extends BaseAction
{
    public function __construct(
        private readonly GetWidgetsHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->resourceResponse(
            resource: WidgetResource::class,
            data: $this->handler->handle($organizerId, null),
        );
    }
}
