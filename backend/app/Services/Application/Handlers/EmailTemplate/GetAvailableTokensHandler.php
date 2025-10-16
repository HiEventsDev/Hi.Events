<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;

class GetAvailableTokensHandler
{
    public function __construct(
        private readonly LiquidTemplateRenderer $liquidRenderer
    ) {
    }

    public function handle(EmailTemplateType $templateType): array
    {
        return $this->liquidRenderer->getAvailableTokens($templateType);
    }
}