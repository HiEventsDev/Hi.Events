<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Exceptions\SsoException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Request\Auth\Sso\CompleteSsoLoginRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Auth\Sso\CompleteSsoLoginHandler;
use Illuminate\Http\JsonResponse;

class CompleteSsoLoginAction extends BaseSsoAction
{
    public function __construct(
        private readonly CompleteSsoLoginHandler $handler,
    ) {}

    public function __invoke(CompleteSsoLoginRequest $request): JsonResponse
    {
        try {
            $loginResponse = $this->handler->handle(
                loginTicket: $request->validated('login_ticket'),
                binding: $request->validated('binding'),
                accountId: (int) $request->validated('account_id'),
            );
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts, $loginResponse->user);
    }
}
