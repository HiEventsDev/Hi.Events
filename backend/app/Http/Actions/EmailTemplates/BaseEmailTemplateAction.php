<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\PreviewEmailTemplateDTO;
use HiEvents\Services\Application\Handlers\EmailTemplate\PreviewEmailTemplateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

abstract class BaseEmailTemplateAction extends BaseAction
{
    protected function validateEmailTemplateRequest(Request $request): array
    {
        return $request->validate([
            'template_type' => ['required', new Enum(EmailTemplateType::class)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cta' => ['nullable', 'array'],
            'cta.label' => ['required_with:cta', 'string', 'max:100'],
            'cta.url_token' => ['required_with:cta', 'string', 'max:50'],
        ]);
    }

    protected function validateUpdateEmailTemplateRequest(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cta' => ['nullable', 'array'],
            'cta.label' => ['required_with:cta', 'string', 'max:100'],
            'cta.url_token' => ['required_with:cta', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }

    protected function validatePreviewRequest(Request $request): array
    {
        return $request->validate([
            'template_type' => ['required', new Enum(EmailTemplateType::class)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cta' => ['nullable', 'array'],
            'cta.label' => ['required_with:cta', 'string', 'max:100'],
            'cta.url_token' => ['required_with:cta', 'string', 'max:50'],
        ]);
    }

    protected function handlePreviewRequest(Request $request, PreviewEmailTemplateHandler $handler): JsonResponse
    {
        $validated = $this->validatePreviewRequest($request);

        $preview = $handler->handle(
            new PreviewEmailTemplateDTO(
                subject: $validated['subject'],
                body: $validated['body'],
                template_type: EmailTemplateType::from($validated['template_type']),
                cta: $validated['cta'] ?? null,
            )
        );

        return $this->jsonResponse($preview);
    }
}
