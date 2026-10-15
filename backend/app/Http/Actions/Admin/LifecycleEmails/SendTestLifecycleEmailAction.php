<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\LifecycleEmails;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Exceptions\LifecycleEmailSendingNotConfiguredException;
use HiEvents\Http\Request\Admin\LifecycleEmail\LifecycleEmailContentRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\SendTestLifecycleEmailHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SendTestLifecycleEmailAction extends BaseLifecycleEmailAction
{
    public function __construct(
        private readonly SendTestLifecycleEmailHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(LifecycleEmailContentRequest $request, string $nudgeType): Response|JsonResponse
    {
        $this->requireSuperAdmin();

        try {
            $this->handler->handle(
                $this->contentFromRequest($request, $nudgeType),
                $this->getAuthenticatedUser()->getEmail(),
                $this->getAuthenticatedUser()->getId(),
            );
        } catch (EmailTemplateValidationException $e) {
            throw $this->toValidationException($e);
        } catch (LifecycleEmailSendingNotConfiguredException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }
}
