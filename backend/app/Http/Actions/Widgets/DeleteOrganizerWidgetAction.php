<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Widgets;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Widget\DeleteWidgetHandler;
use Illuminate\Http\Response;

class DeleteOrganizerWidgetAction extends BaseAction
{
    public function __construct(
        private readonly DeleteWidgetHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $organizerId, int $widgetId): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $this->handler->handle($widgetId, $organizerId, null);

        return $this->deletedResponse();
    }
}
