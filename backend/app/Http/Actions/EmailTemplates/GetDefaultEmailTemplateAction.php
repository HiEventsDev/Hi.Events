<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetDefaultEmailTemplateAction extends BaseAction
{
    public function __construct(
        private readonly EmailTemplateService $emailTemplateService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $defaults = [];

        foreach (EmailTemplateType::cases() as $type) {
            $template = $this->emailTemplateService->getDefaultTemplate($type);
            $defaults[$type->value] = [
                'type' => $type->value,
                'subject' => $template['subject'],
                'body' => $template['body'],
                'cta' => $template['cta'] ?? null,
            ];
        }

        return $this->jsonResponse($defaults);
    }
}
