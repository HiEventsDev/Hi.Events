<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\LifecycleEmails;

use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Admin\LifecycleEmail\LifecycleEmailContentRequest;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\LifecycleEmailContentDTO;
use Illuminate\Validation\ValidationException;

abstract class BaseLifecycleEmailAction extends BaseAction
{
    protected function contentFromRequest(LifecycleEmailContentRequest $request, string $nudgeType): LifecycleEmailContentDTO
    {
        return new LifecycleEmailContentDTO(
            nudgeType: LifecycleNudgeType::fromName($nudgeType),
            subject: $request->validated('subject'),
            body: $request->validated('body'),
            ctaLabel: $request->validated('cta_label'),
        );
    }

    protected function toValidationException(EmailTemplateValidationException $e): ValidationException
    {
        return ValidationException::withMessages($e->validationErrors);
    }
}
