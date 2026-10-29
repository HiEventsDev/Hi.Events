<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoExchangeStatus;
use HiEvents\Exceptions\SsoException;
use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Auth\Sso\SsoExchangeResource;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\SsoExchangeResult;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoStartResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

abstract class BaseSsoAction extends BaseAuthAction
{
    protected function ssoErrorResponse(SsoException $exception): JsonResponse
    {
        $isUserFacing = ! in_array($exception->errorCode, [SsoErrorCode::TOKEN_INVALID, SsoErrorCode::PROVIDER_UNAVAILABLE, SsoErrorCode::ISSUER_NOT_ALLOWED], true);

        Log::info('SSO request failed', array_filter([
            'error_code' => $exception->errorCode->value,
            'reason' => $isUserFacing ? null : $exception->getMessage(),
        ]));

        return $this->jsonResponse([
            'message' => $isUserFacing ? $exception->getMessage() : __('We couldn\'t sign you in. Please try again.'),
            'error_code' => $exception->errorCode->value,
            'context' => (object) $exception->context,
        ], $this->statusFor($exception->errorCode));
    }

    protected function exchangeResponse(SsoExchangeResult $result): JsonResponse
    {
        return $this->addTokenToResponse(
            $this->resourceResponse(SsoExchangeResource::class, $result),
            $result->status === SsoExchangeStatus::AUTHENTICATED ? $result->loginResponse?->token : null,
        );
    }

    protected function startResponse(SsoStartResult $result): JsonResponse
    {
        return $this->jsonResponse([
            'authorization_url' => $result->authorizationUrl,
            'binding' => $result->binding,
        ], wrapInData: true);
    }

    protected function signInMethodsImpersonationResponse(): JsonResponse
    {
        return $this->impersonationForbiddenResponse(__('Sign-in methods cannot be changed while impersonating a user.'));
    }

    private function statusFor(SsoErrorCode $code): int
    {
        return match ($code) {
            SsoErrorCode::PROVIDER_NOT_CONFIGURED, SsoErrorCode::NOT_SET_UP => ResponseCodes::HTTP_NOT_FOUND,
            SsoErrorCode::ISSUER_NOT_ALLOWED => ResponseCodes::HTTP_UNPROCESSABLE_ENTITY,
            SsoErrorCode::PROVIDER_UNAVAILABLE => ResponseCodes::HTTP_SERVICE_UNAVAILABLE,
            SsoErrorCode::STATE_INVALID, SsoErrorCode::TICKET_INVALID, SsoErrorCode::TOKEN_INVALID => ResponseCodes::HTTP_UNAUTHORIZED,
            SsoErrorCode::REGISTRATION_DISABLED => ResponseCodes::HTTP_FORBIDDEN,
            SsoErrorCode::EMAIL_CODE_INVALID => ResponseCodes::HTTP_UNPROCESSABLE_ENTITY,
            default => ResponseCodes::HTTP_CONFLICT,
        };
    }
}
