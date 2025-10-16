<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Services\Application\Handlers\EmailTemplate\PreviewEmailTemplateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewEventEmailTemplateAction extends BaseEmailTemplateAction
{
    public function __construct(
        private readonly PreviewEmailTemplateHandler $handler
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->handlePreviewRequest($request, $this->handler);
    }
}