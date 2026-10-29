<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\LifecycleEmails;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Http\Request\Admin\LifecycleEmail\LifecycleEmailContentRequest;
use HiEvents\Resources\LifecycleEmail\LifecycleEmailPreviewResource;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\PreviewLifecycleEmailHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PreviewLifecycleEmailAction extends BaseLifecycleEmailAction
{
    public function __construct(
        private readonly PreviewLifecycleEmailHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(LifecycleEmailContentRequest $request, string $nudgeType): JsonResponse
    {
        $this->requireSuperAdmin();

        try {
            $preview = $this->handler->handle($this->contentFromRequest($request, $nudgeType));
        } catch (EmailTemplateValidationException $e) {
            throw $this->toValidationException($e);
        }

        return $this->resourceResponse(
            resource: LifecycleEmailPreviewResource::class,
            data: $preview,
        );
    }
}
