<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Resources\EmailTemplateResource;
use HiEvents\Services\Application\Handlers\EmailTemplate\GetEmailTemplatesHandler;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\GetEmailTemplatesDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class GetOrganizerEmailTemplatesAction extends BaseAction
{
    public function __construct(
        private readonly GetEmailTemplatesHandler $handler
    ) {
    }

    public function __invoke(Request $request, int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $request->validate([
            'template_type' => ['nullable', new Enum(EmailTemplateType::class)],
            'include_inactive' => ['in:true,false'],
        ]);

        $templates = $this->handler->handle(
            new GetEmailTemplatesDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                event_id: null,
                template_type: isset($validated['template_type'])
                    ? EmailTemplateType::from($validated['template_type'])
                    : null,
                include_inactive: $validated['include_inactive'] ?? false,
            )
        );

        return $this->resourceResponse(
            resource: EmailTemplateResource::class,
            data: $templates,
        );
    }
}
