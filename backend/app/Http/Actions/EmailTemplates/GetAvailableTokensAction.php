<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\EmailTemplate\GetAvailableTokensHandler;
use Illuminate\Http\JsonResponse;

class GetAvailableTokensAction extends BaseAction
{
    public function __construct(
        private readonly GetAvailableTokensHandler $handler
    ) {
    }

    public function __invoke(string $templateType): JsonResponse
    {
        //no authorization needed

        $type = EmailTemplateType::tryFrom($templateType);

        if (!$type) {
            return $this->jsonResponse(['error' => __('Invalid template type')], ResponseCodes::HTTP_BAD_REQUEST);
        }

        $tokens = $this->handler->handle($type);

        return $this->jsonResponse(['tokens' => $tokens]);
    }
}
