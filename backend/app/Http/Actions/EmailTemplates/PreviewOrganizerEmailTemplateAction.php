<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Services\Application\Handlers\EmailTemplate\PreviewEmailTemplateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[AllowedForReadOnlyUsers]
class PreviewOrganizerEmailTemplateAction extends BaseEmailTemplateAction
{
    public function __construct(
        private readonly PreviewEmailTemplateHandler $handler
    ) {}

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->handlePreviewRequest($request, $this->handler);
    }
}
