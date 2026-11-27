<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Exceptions\SignupVerificationFailedException;
use HiEvents\Enterprise\Crm\Http\Request\SubmitSignupFormRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO\SubmitSignupFormDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\SubmitSignupFormHandler;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SubmitSignupFormPublicAction extends BaseAction
{
    public function __construct(
        private readonly SubmitSignupFormHandler $handler,
    ) {}

    public function __invoke(SubmitSignupFormRequest $request, string $publicId): JsonResponse
    {
        try {
            $this->handler->handle(new SubmitSignupFormDTO(
                public_id: $publicId,
                email: $request->validated('email'),
                first_name: $request->validated('first_name'),
                last_name: $request->validated('last_name'),
                honeypot: $request->validated('website'),
                turnstile_token: $request->validated('turnstile_token'),
                ip_address: $this->getClientIp($request),
                user_agent: $request->userAgent(),
                locale: $request->validated('locale') ?? app()->getLocale(),
            ));
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (SignupVerificationFailedException $exception) {
            throw ValidationException::withMessages(['turnstile_token' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['status' => 'check_inbox'], Response::HTTP_ACCEPTED);
    }
}
