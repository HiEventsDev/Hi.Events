<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Request\Auth\Sso\SendSsoSignupCodeRequest;
use HiEvents\Services\Application\Handlers\Auth\Sso\SendSsoSignupCodeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SendSsoSignupCodeAction extends BaseSsoAction
{
    public function __construct(
        private readonly SendSsoSignupCodeHandler $handler,
    ) {}

    public function __invoke(SendSsoSignupCodeRequest $request): Response|JsonResponse
    {
        try {
            $this->handler->handle(
                signupTicket: $request->validated('signup_ticket'),
                binding: $request->validated('binding'),
                locale: $request->validated('locale'),
            );
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        }

        return $this->noContentResponse();
    }
}
