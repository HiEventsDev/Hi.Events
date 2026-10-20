<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\LifecycleEmails;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Http\Request\Admin\LifecycleEmail\UpdateLifecycleEmailRequest;
use HiEvents\Resources\LifecycleEmail\AdminLifecycleEmailResource;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\UpdateLifecycleEmailHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateLifecycleEmailAction extends BaseLifecycleEmailAction
{
    public function __construct(
        private readonly UpdateLifecycleEmailHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(UpdateLifecycleEmailRequest $request, string $nudgeType): JsonResponse
    {
        $this->requireSuperAdmin();

        try {
            $email = $this->handler->handle(
                $this->contentFromRequest($request, $nudgeType),
                $request->boolean('enabled'),
            );
        } catch (EmailTemplateValidationException $e) {
            throw $this->toValidationException($e);
        }

        return $this->resourceResponse(
            resource: AdminLifecycleEmailResource::class,
            data: $email,
        );
    }
}
